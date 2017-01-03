<?php
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Yaml\Parser;

class AcquiaHelper {
  public $debug = true;

  //store the results of the processed YAML file here.
  public $yaml = array();

  public $output = null;

  public function __construct() {
      //probably should move some setup to here
    $output = new ConsoleOutput(true);
  	$this->setOutput($output);
  }

  public function setOutput(ConsoleOutput $output) {
    if (!is_object($this->output)) {
    	$this->output = $output;
    }
  }

  /**
   * Searches array of strings for placeholder values and tries to replace
   * them using keys defined elsewhere in the same array
   * @param  array  $values   array of key : value pairs from our yaml file.
   * @param  boolean $child   Childnode flag
   * @param  array  $parent   Parent array incase we have child nodes
   * @return array            All placeholder values should be replaced.
   *
   * You might look at improving this with http://php.net/manual/en/function.strtr.php
   */
  public function getReplacedValues($values, $child=false, $parent=array(), $src=null, $dst=null) {

    $parent = ($child) ? $parent : $values;
    $tmp = null;

    if ($dst!==null && $src !==null && $child==false) {
      $values['SRC_ENV'] = strtolower($src);
      $values['DST_ENV'] = strtolower($dst);
    }

    foreach ($values as $key => $value) {
      //we keep working on the tmp sting until all values are changed, then put tmp into original values index.
      $tmp = $value;
      if (is_string($value)) {
        if (preg_match_all('/{{(.*?)}}/', $value, $m)) {
          foreach ($m[1] as $i => $varname) {
            //use strstr to replace values without the left to right override issues.
            $tmp = str_replace($m[0][$i], $this->findKey($varname, $values, $parent), $tmp);
          }
          $values[$key] = $tmp;
        }
      } elseif (is_array($value)) {
        //if it is the root node, we want to send the modified values to the child as the parent, otherwise send the parent
        $send = (!$child) ? $values : $parent;
        $values[$key] = $this->getReplacedValues($value,true,$send, $src, $dst);
      }
    }

		//move environment to top
		if (isset($values['ENVIRONMENTS']) && ($dst!==null && $src !==null) && ($child == false)) {
			 $SRC_ENV  = strtoupper($src);
			 $DST_ENV  = strtoupper($dst);
			 //map the environments to the toplevel array for src
			 $src_tmp  = $values['ENVIRONMENTS'][$SRC_ENV];
			 $src_tmp = $this->remapKeys('SRC_', $src_tmp);

			 //map the environments to the toplevel array for dst
			 $dst_tmp  = $values['ENVIRONMENTS'][$DST_ENV];
			 $dst_tmp = $this->remapKeys('DST_', $dst_tmp);

			 $values = array_merge($values, $dst_tmp, $src_tmp);
		}

	 	//print_r($values);
   	return $values;
  }

  public function remapKeys($name, $array) {
    foreach ($array as $key => $value) {
        $array[$name.$key] = $value;
        unset($key);
    }

    return $array;
  }

  /**
   * Look for the key in the closest tree near me, if can't find try my parent
   * @param  string $key      [description]
   * @param  array $current   [description]
   * @param  array $parent    [description]
   * @return array|string     [description]
   */
  public function findKey($key, $current, $parent) {
        if (array_key_exists($key, $current)) {
          return $current[$key];
        } elseif (array_key_exists($key, $parent)) {
          return $parent[$key];
        } else {
          //lets return the key back the way it was, so we know something is wrong.
          return '{{'.$key.'}}';
        }
  }

  /**
   * Returns all the DB information from Acquia API.  We could make this more useful
   * by making it less specific and allowing for other API requests?
   * @return [type] some sort of returned data, maybe array
   */
  public function getDatabase($env, $sitegroup, $values, $cache=false) {
  	//print_r($values);
  	$userpwd = $values['CLOUD_UUID'].":".$values['CLOUD_KEY'];
  	$url ='https://cloudapi.acquia.com/v1/sites/prod:'.$sitegroup.'/envs/'.$values['CLOUD_REALM'].'/dbs.json';
    $cachefile = __DIR__ . '/cache/'.strtolower($env). '_acquia_db.cache';
    $this->output->writeln($url);
  	$cachetime = 3600; //one hour


    if ($cache && file_exists($cachefile) && ( (time() - $cachetime) < filemtime($cachefile))) {
  			//pick from file
  			$file = file_get_contents($cachefile);
  			$data = json_decode($file);
  			if (is_array($data)) {
  				return $data;
  			}
  	}

    $result = $this->getCurlRequest($userpwd, $url);

		if ($data = json_decode($result, false)) {

      if ($cache) {
        $filestring = file_put_contents($cachefile, $result);
  			if (!$filestring) {
  				echo "Error writing cache file.";
  			}
      }
      return $data;
	}

  	return false;
  }

  //https://cloudapi.acquia.com/v1/sites/realm:mysite/envs/dev/dbs/mysite/backups.json
  public function saveBackup($domain, $env, $sitegroup, $values) {
    //print_r($values);
    $userpwd = $values['CLOUD_UUID'].":".$values['CLOUD_KEY'];
    $url ='https://cloudapi.acquia.com/v1/sites/prod:'.$sitegroup.'/envs/'.$values['CLOUD_REALM'].'/dbs/'.$sitegroup.'/backup.json';

    $this->output->writeln($url);

    $variables = array(
        'site' => $sitegroup,
        'env' => $env,
        'domain' => $domain
    );
    //'{+base_path}/sites/{site}/envs/{env}/domains/{domain}.json'
    $result = $this->getCurlRequest($userpwd, $url, false, $variables);

    if ($data = json_decode($result, false)) {
      return $data;
    }
    $this->output->writeln('Error in completing the database backup command.');
    return false;
  }



  public function setDomain($domain, $env, $sitegroup, $delete=false) {
    $userpwd	= $this->yaml['CLOUD_UUID'].":".$this->yaml['CLOUD_KEY'];
    $url			='https://cloudapi.acquia.com/v1/sites/prod:'.$sitegroup.'/envs/'.$env.'/domains/'.$domain.'.json';
    //https://cloudapi.acquia.com/v1/sites/prod:ucsfp/envs/dev/domains/apitest.ucsf.com.json
    $this->output->writeln($url);

    $variables = array(
    		'site' => $sitegroup,
    		'env' => $env,
    		'domain' => $domain
    );
    //'{+base_path}/sites/{site}/envs/{env}/domains/{domain}.json'


    $result = $this->getCurlRequest($userpwd, $url, $delete, $variables);

    if ($data = json_decode($result, false)) {
      return $data;
    }
    $this->output->writeln('Error in completing the setDomain command.');
    return false;
  }

  public function getCurlRequest($userpwd, $url, $delete=false, $data=false) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20); //timeout after 30 seconds
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch,  CURLOPT_SSL_VERIFYHOST,0);

    if ($delete) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    if ($data) {
    	curl_setopt($ch, CURLOPT_POST, 1);
    	curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
    	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
    $result=curl_exec($ch);
    if (curl_error($ch)) {
      echo curl_error($ch);
      curl_close($ch);
      return false;
    }
    curl_close($ch);
    return $result;
  }

  /**
   * Call the Acquia API and look for the database that matches as close as
   * possible to the domain name we are looking for, use exceptions if neccessary
   * @param  [type] $name [description]
   * @return [type]       [description]
   */
  public function getSiteDb($name, $env, $sitegroup, $values, $cache = false) {
    $env  = strtolower($env);
    $data = $this->getDatabase($env, $sitegroup, $values, $cache);

    if (isset($name)) {
    	$needle = array('.ucsf.edu','.com','dev.','www.','stage.','test.','preview.');
    	$name = str_replace($needle, '', filter_var($name,FILTER_SANITIZE_STRING,FILTER_NULL_ON_FAILURE));

    	$searchable = array();

    	foreach ($data as $domain) {
    		$searchable[$domain->name] 	= $domain;
    	}

			//echo 'Looking for: '.$name."\n";

			//should deal with mappings first, otherwise it might pull database for wrong website.
			$dbmapping = $this->returnMappings();

			//echo 'Looking for a mapping of: '.$name."\n";
			if (array_key_exists($name, $dbmapping)) {
					$dbname = $dbmapping[$name];
					return $searchable[$dbname];
			}

			//now look for regular options
    	if (array_key_exists($name, $searchable)) {
    			return $searchable[$name];
    	} else {
    			//try changing underscore domains to no spaces
    			$tmpname = trim(str_replace('_','',$name));
    			if (array_key_exists($tmpname, $searchable)) {
    					return $searchable[$tmpname];
    			}
    			//try changing hyphened domains to underscores
    			$tmpname = trim(str_replace('-','_',$name));
    			if (array_key_exists($tmpname, $searchable)) {
    					return $searchable[$tmpname];
    			}
    	}
    }

		  //we should just fail here or create the database;
			$this->output->writeln('Failed to find the site database:'.$name);
			return false;
  }

	public function returnMappings() {
		//try a direct mapping for odd side cases - exceptions
		$dbmapping = array(
        'drupal'						=>'ucsf_drupal',
        'paetc'							=>'aids_education_training',
        'policies'					=>'campus_policies',
        'coi'								=>'conflict_of_interest',
        'fhop'							=>'family_health_outcomes',
        'familyhealthcenter'=>'FamilyHealthCenter',
        'finance.medschool'	=>'finance',
        'hrp'								=>'human_resource_protection',
        'mrc'								=>'multicultural_resource_center',
        'postdocs'					=>'postdocs_ucsf_edu',
        'rrp'								=>'research_resource_program',
        'safemotherhood'		=>'safemotherhood.ucsf.edu',
        'osr'								=>'sponsored_research',
        'dentistry'					=>'school_dentistry',
        'emergency'					=>'emergency_medicine',
        'hub'								=>'clinicalhub',
        'itgov'							=>'it_gov',
        'kidscancertrials'	=>'kidscancer',
        'rna.keck'					=>'keck',
        'willedbodyprogram'	=>'willed_body',
        'pharm'							=> 'parmsites',
        'bts' 							=> 'bioengineering',
        'www3'							=>'emergency',
        'evcprovost'				=>'provost',
        'livercenter'				=>'emergency',
        'mccormicklab'			=>'mccormick',
        'socpop'						=>'parc',
        'ahi'								=>'ahi_ucsf_edu',
        'bushlab'						=>'Bushlab',
        'academicaffairs.medschool'	=>'academicaffairs',
        'andino'						=>'andino_lab',
        'addictionresearch'	=>'addiction_research',
        'dc'	              =>'datacenter',
        'depressioncenter'	=>'depression_center',
        'digitalaccess'	    =>'awareness',
        'knoxlab'	          =>'knoxlab',
        'kleinlab'	        =>'kleinlab',
        'itspconference'	  =>'itsp_conference',
        'it-dmp'	          =>'itdmp',
        'lanierlab'	        =>'lanier_lab',
        'koliwadlab'	      =>'koliwad',
        'qbcmaster'	        =>'qbc_master',
        'odonovanlab'       =>'telolab',
        'pdcenter.neurology'=>'pdcenter',
        'odonovanlab'       =>'telolab',
        'pharm'             =>'pharmsites',
        'policies.medschool'=>'policies',
        'postdocs.medschool'=>'postdocs',
        'prepare'           =>'preparepublic',
        'sf4health.org'     =>'sf4health',
        'meded'             =>'medical_education',
        'studentaffairs'    =>'studentaffairs',
        'qb3.org'           =>'qb3',
        'archive.missionbayhospitals'	=>'missionbayhospitals',
        'health-eyou'				=>'healtheyou',
        'irb'								=>'human_research_protection',
        'hrpp'							=>'human_research_protection',
        'surgery.dermatology'=>'dermsurgery',
        'zsfganes'					=>'sfghanes',
        'radiology-help'		=>'radiology_help',
        'radiology-internal'=>'radiology_internal',
        'ciapm.org'					=>'ciapm_org'
		);

		return $dbmapping;
	}

  public function createDatabase($dbname, $env) {
  	//'https://cloudapi.acquia.com/v1/sites/prod:'.$sitegroup.'/envs/'.$values['CLOUD_REALM'].'/dbs.json';
  	$url 		= "https://cloudapi.acquia.com/v1/sites/prod:".$this->yaml['ACQUIA_SITEGROUP_DST']."/dbs.json";
  	$userpwd	= $this->yaml['CLOUD_UUID'].":".$this->yaml['CLOUD_KEY'];

  	$data = '{"db":"'.$dbname.'"}';

  	$response = $this->getCurlRequest($userpwd, $url, false, $data);
  	$this->output->writeln('AQUIA API RESPONSE: '.$response."\n");

  	$apireturn = json_decode($response, false);
  	//$env  = strtolower($env);
  	//$name, $env, $sitegroup, $values, $cache
  	//$data = $this->getSiteDb($dbname, $env, $this->yaml['ACQUIA_SITEGROUP_DST'], $this->yaml, false);
  	$this->yaml['ACQUIA_DB']  = $dbname;
  	putenv('ACQUIA_DB='.$dbname);

  	return $apireturn;
  }

  /**
   * Set environmental variables for the BASH shell
   * @param array $env    Array of environmental variables to set
   * @param OutputInterface $output Output object from Cilex
   */
  public function setEnvironmentals($env) {

      foreach ($env AS $key => $val) {
        if (!is_array($val) && (!empty($val) || $val===0 )) {
            putenv($key.'='.$val);
            $this->yaml[$key]= $val;
        }
      }
      //confirm this has worked
      $out = shell_exec('echo ${ACQUIA_SITEGROUP}');
      if ($out) {
        $this->output->writeln('Environmental variables are now set.');
        return true;
      } else {
        return false;
      }
  }

  public function importEnviromentalVariables($src, $dst) {

		  //read the YAML file
		$yaml = new Parser();
		$values = $yaml->parse(file_get_contents('./config.yaml'));
		if (!is_array($values)) {
			exit('Could not read config.yaml file.');
		}

		$this->output->writeln('Reading YAML file for environmental variables.');
		$src = strtoupper($src);

		if (!isset($src) && !in_array($src, $values['ENVIRONMENTS'])) {
			$this->output->writeln('No valid source environment set in config.yaml');
			return false;
		}

		if (is_array($values)) {
			$values = $this->getReplacedValues($values, false, array(), $src, $dst);
			$this->setEnvironmentals($values);
		}
	}


  /* Database response from Acquia
   stdClass Object
   (
	   [name] => ucsf_drupal
	   [instance_name] => ucsfpdb8950 -- database name
	   [username] => ucsfp
	   [password] => VyLvuwbJy3Xj4JV
	   [host] => ded-961  -- this is the host locally, add port 3306
	   [db_cluster] => 253
   )
   */
  public function getCurrentDB($sitename, $src, $dst) {
  	//we need the sitegroup for the correct database connection.
  	//this will change on the SHEILD environment from ucsfp probably to ucsfp1 or similar.
  	$dbinfo = $this->getSiteDb($sitename, $src, $this->yaml['ACQUIA_SITEGROUP'], $this->yaml, false);

  	if ($dbinfo === false) {
  		$this->output->writeln('Could not establish the correct DB from Acquia Cloud API for: '.$sitename);
			//die("APPLICATION DIED HERE: ".__LINE__);
  	}

  	$this->output->writeln('Reading database values for source server.');
  	return $dbinfo;
  }


  public function executeShellScript($script, $line=false) {
    //just confirm this is a file
    if (file_exists($script) && $line==false) {
			$this->output->writeln("Running requested bash file ({$script}) in bash shell.");
      $this->runScriptRealtime($script);
    } else {
			//executive one line
			$this->output->writeln(shell_exec($script));
    }
  }

  /**
   * This successfully buffers the content to the PHP script while executing.
   * Allows you to see progression of any BASH shell script during the process.
   * This is the __MAGIC__ that makes BASH/PHP play nice.
   *
   * @param  string $cmd    File or string of the script
   * @param  object $output References our OutputInterface $output of Symfony
   * @return null
   */
  public function runScriptRealtime($file) {

		if (!file_exists($file)) {
			$this->output->writeln('Bash file does not exist.');
		}

		while (@ ob_end_flush()); // end all output buffers if any

    $proc = popen($file, 'r');

    while (!feof($proc))
    {
        $this->output->writeln(fread($proc, 4096));
        @ flush();
    }
  }

	/**
	 * Find any substring in an indexed array of partial strings.
	 * @param  array  $haystack  Array of strings to search
	 * @param  string  $needles   String you are looking for.
	 * @param  boolean  $sensitive Case sensitive string search
	 * @param  integer $offset    String search offset
	 * @return boolean
	 */
  public function stringExists($haystack, $needles, $sensitive=false, $offset=0) {
    foreach($needles as $needle) {
        $tmpresult = ($sensitive) ? strpos($haystack, $needle, $offset) : stripos($haystack, $needle, $offset);
        if (!($tmpresult===false)) {
          return true;
        }
    }
    return false;
  }

}
