# Shield Migration Tools

This is a suite of command line utilities, run from 
`php migrate2shield.php commandName --options source destination`

This tool was written to automate much of the process of getting sites exported from the Acquia non-shield environment to the Shield environment. 

##Common commands include:

Most of the commands require a FILE as well as SOURCE and DESTINATION server names that our stored in the YAML file.

**getdb** - get the site database information

**exportDB** - exports DBs in Acquia via the Acquia API, getting the site name from the source file, then moving the database from the source server to the destination server.  This also creates the export_progress.csv files that will be used on the DESTINATION server.

**dbImport** - imports DB using the import file references the database files on the server, this is a pipe delimited file, which contains the DB sql.gz file name, the source database destination name and some other details.   See export_progress_sample.csv


**fileImport** - uses the same pipe delimited file as dbImport to import the source files to the destination server.   This includes both /files and /files-private directories for the given domain(s). 

**moveDomain** - moves the actual Acquia virtual host from the source server to the destination server.  Provides options to setup preview domains, if needed as well as to just delete the virtual host.

**makeDatabases** - this needs to run prior to the dbImport command as it needs to create all the databases on the new server. Uses the same pipe delimited input file.  See export_progress_sample.csv

