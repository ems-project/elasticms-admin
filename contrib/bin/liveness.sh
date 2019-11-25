#!/bin/bash
source /opt/bin/setenv.sh

cd  /opt/src
/opt/src/vendor/bin/phpunit
                                                 
_file="/var/lib/ems/dumps/dump*.sql"                                       
                                                                                
#if test `find "$_file" -mmin -190`                           
#then                                                                            
#        echo "No recent backup (in the last 3:10)"                                        
#        exit 113                                     
#fi
