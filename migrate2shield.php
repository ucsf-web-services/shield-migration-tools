<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/acquia_helper.php';

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

$app = new Silly\Application();

$acquia = new AcquiaHelper();

$app->command('getdb [name]', function ($name, OutputInterface $output) use ($acquia) {
    if ($name) {
        $output->writeln("{$name}\n");
        $response = $acquia->getSiteDb($name);
        $output->writeln(print_r($response, true));
    }
});

$app->command('setenvfromyaml', function (InputInterface $input, OutputInterface $output) use ($acquia) {

    $src = $input->getArgument('src');
    $dst = $input->getArgument('dst');

    $acquia->importEnviromentalVariables($src, $dst);
});

$app->command('exportDB file src dst', function (InputInterface $input, OutputInterface $output) use ($acquia) {
    $file = $input->getArgument('file');
    $src = $input->getArgument('src');
    $dst = $input->getArgument('dst');
    // $yell = $input->getOption('source');
    // $acquia->setOutput ( $output );
    $valid_env = array(
        'prod',
        'test',
        'dev',
        'oldprod',
        'oldtest',
        'olddev'
    );

    if (! in_array($dst, $valid_env)) {
        $output->writeln('destination environment is invalid, please use dev, test, prod');
        return false;
    }

    if (! in_array($src, $valid_env)) {
        $output->writeln('Source environment is invalid, please use dev, test, prod');
        return false;
    }

    if (is_file($file) && ($file = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
        foreach ($file as $index => $site) {
            $site = trim($site);
            $acquia->importEnviromentalVariables($src, $dst);
            $dbinfo = $acquia->getCurrentDB($site, $src, $dst);

            if (! is_object($dbinfo)) {
                $output->writeln("Failed to get DB because \$dbinfo is not an object.");
                continue;
            }

            $dbvalues['ACQUIA_DB'] = $dbinfo->name;
            $dbvalues['SRC_DB_SETTING_NAME'] = $dbinfo->name;
            $dbvalues['SRC_DB_USER'] = $dbinfo->username;
            $dbvalues['SRC_DB_HOST'] = $dbinfo->host;
            $dbvalues['SRC_DB_NAME'] = $dbinfo->instance_name;
            $dbvalues['SRC_DB_PASS'] = $dbinfo->password;
            $dbvalues['MIGRATION_SITE_NAME'] = $site;

            $acquia->setEnvironmentals($dbvalues);

            $acquia->executeShellScript('./bashes/source_db.sh');
        }

        // should then move the export_progress.csv file to the destination server using shell.
    } else {
        $output->writeln('Invalid source file.');
        return false;
    }
})
    ->descriptions('Will export the Database for each site given and copy them to the destination server. ', array(
    // $site, $acquia_db, $dbname, $db_import_file
    'file' => 'File containing each site to export on one line.',
    'src' => 'Source server as defined in YAML',
    'dst' => 'Destination server as defined in YAML'
));

/**
 * This script should run all the exports for the given source and destination
 * based on the sites file given.
 * The sites file should be single column
 * delimited list, with each site we plan to move matching its site folder
 * in the /docroot/sites/* directory.
 * Tasks:
 * 1) Create the database for the current site in the export file
 * 2) import the databases from the exported DB file, the file created from export should
 * include the site name, the exported database name and other information.
 * 2) run the rync for each site /docroot/sites/site.ucsf.edu/files/ directory
 * 3) setup the domain through the Acquia API
 */
$app->command('dbImport file src dst', function (InputInterface $input, OutputInterface $output) use ($acquia) {
    // $acquia->setOutput ( $output );
    $file = $input->getArgument('file');
    $src = $input->getArgument('src');
    $dst = $input->getArgument('dst');
    // $yell = $input->getOption('source');
    // drupal.ucsf.edu|ucsf_drupal|ucsfpdevdb8948|ucsfpdevdb8948.2016-02-17-1455672135.sql.gz

    $env_set = false;

    $valid_env = array(
        'prod',
        'test',
        'dev',
        'oldprod',
        'oldtest',
        'olddev'
    );

    if (! in_array($dst, $valid_env)) {
        $output->writeln('destination environment is invalid, please use dev, test, prod');
        return false;
    }

    if (! in_array($src, $valid_env)) {
        $output->writeln('Source environment is invalid, please use dev, test, prod');
        return false;
    }

    if (is_file($file) && ($file = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
        foreach ($file as $index => $row) {

            list ($site, $acquia_db, $dbname, $db_import_file) = explode('|', $row);

            $acquia->importEnviromentalVariables($src, $dst, false);
            // $acquia->createDatabase($acquia_db, $dst);

            putenv("MIGRATION_SITE_NAME={$site}");
            putenv("SRC_DB_SETTING_NAME={$acquia_db}");
            putenv("ACQUIA_DB={$acquia_db}");
            putenv("RAW_DB_NAME={$dbname}");
            putenv("RAW_DB_FILE={$db_import_file}");

            $acquia->executeShellScript('bashes/import_db.sh');
        }
    } else {
        $output->writeln('Invalid source file.');
        return false;
    }
})
    ->descriptions('Import each database into Acquia', array(
    // $site, $acquia_db, $dbname, $db_import_file
    'file' => 'Pipe delimited file which includes the Site name, Acquia DB name, Real DB Name and DB Source file (MySQL dump file).',
    'src' => 'Source server as defined in YAML',
    'dst' => 'Destination server as defined in YAML'
));
// , then Rync the files directories for files and files-private of each site.

$app->command('fileImport file src dst', function (InputInterface $input, OutputInterface $output) use ($acquia) {
    // $acquia->setOutput ( $output );
    $env_set = false;
    $file = $input->getArgument('file');
    $src = $input->getArgument('src');
    $dst = $input->getArgument('dst');
    $valid_env = array(
        'prod',
        'test',
        'dev',
        'oldprod',
        'oldtest',
        'olddev'
    );

    if (! in_array($dst, $valid_env)) {
        $output->writeln('destination environment is invalid, please use dev, test, prod');
        return false;
    }

    if (! in_array($src, $valid_env)) {
        $output->writeln('Source environment is invalid, please use dev, test, prod');
        return false;
    }

    if (is_file($file) && ($file = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
        foreach ($file as $index => $row) {
            list ($site, $acquia_db, $dbname, $db_import_file) = explode('|', $row);
            // we determine the site directory here, so we can't have www. or preview. or test.

            $site = trim($site);

            $needle = array(
                'preview.',
                'test.',
                'www.'
            );
            $site = str_replace($needle, '', filter_var($site, FILTER_SANITIZE_STRING, FILTER_NULL_ON_FAILURE));
            $acquia->importEnviromentalVariables($src, $dst, false);

            putenv("MIGRATION_SITE_NAME={$site}");
            putenv("SRC_DB_SETTING_NAME={$acquia_db}");
            putenv("ACQUIA_DB={$acquia_db}");
            putenv("RAW_DB_NAME={$dbname}");
            putenv("RAW_DB_FILE={$db_import_file}");

            $acquia->executeShellScript('bashes/copy_resources.sh');
            // $acquia->executeShellScript('bashes/copy_resources.sh', $output);
        }
    } else {
        $output->writeln('Invalid source file.');
        return false;
    }
})
    ->descriptions('Import the files and file-private into the source website.');

$app->command('makeDatabases file src dst', function (InputInterface $input, OutputInterface $output) use ($acquia) {

    // $acquia->setOutput ( $output );
    $file = $input->getArgument('file');
    $src = $input->getArgument('src');
    $dst = $input->getArgument('dst');

    $acquia->importEnviromentalVariables($src, $dst, false);

    if (is_file($file) && ($file = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
        foreach ($file as $index => $row) {
            list ($site, $acquia_db, $dbname, $db_import_file) = explode('|', $row);

            $output->writeln('Creating database for: ' . $site . ' ACQUIA_DB: ' . $acquia_db);
            $output->writeln("\n");
            $response = $acquia->createDatabase($acquia_db, $dst);

            if (isset($response->description)) {
                $output->writeln($response->description);
            }
        }
    }
});

$app->command('moveDomain [--delete] [--shield] [--preview] [--delay] file src dst', function (InputInterface $input, OutputInterface $output) use ($acquia) {
    // $acquia->setOutput ( $output );
    $file   = $input->getArgument('file');
    $src    = $input->getArgument('src');
    $dst    = $input->getArgument('dst');
    $delete = $input->getOption('delete');

    $shield     = $input->getOption('shield');
    $preview    = $input->getOption('preview');
    $delay      = $input->getOption('delay');
    // drupal.ucsf.edu|ucsf_drupal|ucsfpdevdb8948|ucsfpdevdb8948.2016-02-17-1455672135.sql.gz

    $valid_env = array(
        'prod',
        'test',
        'dev',
        'oldprod',
        'oldtest',
        'olddev'
    );

    if (!in_array($dst, $valid_env)) {
        $output->writeln('destination environment is invalid, please use dev, test, prod');
        return false;
    }

    if (!in_array($src, $valid_env)) {
        $output->writeln('Source environment is invalid, please use dev, test, prod');
        return false;
    }

    $acquia->importEnviromentalVariables($src, $dst, false);

    $cloudAPIIterator = 0;

    if (is_file($file) && ($file = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
        foreach ($file as $index => $row) {
            list ($site) = explode('|', $row);

            if ($shield || $preview)  {
                $prefix =  ($shield) ? 'shield.' : 'preview.';
                $site = $prefix . $site;

            } else {
                if ($dst == 'test') {
                    $site = 'test.' . $site;
                }
                if ($dst == 'dev') {
                    $site = 'dev.' . $site;
                }
            }


            // I think we only need to change one environmental value, but not 100% sure yet.
            putenv("MIGRATION_SITE_NAME={$site}");
            $acquia->yaml['MIGRATION_SITE_NAME'] = $site;

            // remove the old server instance
            $result = $acquia->setDomain($site, $acquia->yaml['SRC_ENV'], $acquia->yaml['ACQUIA_SITEGROUP'], true);

            if ($result) {
                $output->writeln(print_r($result, true));
            }

            $result = false;
            // add domain to new Destination Acquia Server

            $result = $acquia->setDomain($site, $acquia->yaml['DST_ENV'], $acquia->yaml['ACQUIA_SITEGROUP_DST'], $delete);

            if ($result) {
                $output->writeln(print_r($result, true));

                if (!$result->message == 'Resource not found') {
                  $cloudAPIIterator++;
                }
            }

            //Acquia API has some major performance issues, too many consecutive requests and you start getting failures
            if ($delay) {
              if (($cloudAPIIterator % 10) == 0 && $cloudAPIIterator>0) {
                  sleep(600);
              } else {
                  sleep(15);
              }
            }
        }
    } else {
        $output->writeln('Invalid source file.');
        return false;
    }
})
    ->descriptions('Import each database into Acquia', array(
    // $site, $acquia_db, $dbname, $db_import_file
    'file' => 'Pipe delimited file which includes the Site name, Acquia DB name, Real DB Name and DB Source file (MySQL dump file).',
    'src' => 'Source server as defined in YAML',
    'dst' => 'Destination server as defined in YAML'
));

/**
 * This was to confirm the streaming of the bash output was working via PHP.
 * If working the pings should be displayed one at a time just like if you
 * were working on the command line.
 */
$app->command('pingtest', function (InputInterface $input, OutputInterface $output) use ($acquia) {
    // $acquia->setOutput ( $output );
    // $argument = $input->getArgument('name');
    // $option = $input->getOption('source');
    $acquia->runScriptRealtime('ping -c 10 127.0.0.1');
})
    ->descriptions('Test realtime updating of the PHP to BASH shell.');

$app->run();
