#!/bin/bash

                        git config --global --add safe.directory /home/pi/IMAGEN_PHP2
                        cd /home/pi/IMAGEN_PHP2                                             
                        git pull --force                      
                        sudo rm -R /home/pi/A108
                        mkdir /home/pi/A108                                                
                        cp -R /home/pi/IMAGEN_PHP2/* /home/pi/A108
                        cp -R /home/pi/IMAGEN_PHP2/html/ /var/www/
                        sleep 6                                             
                        sudo chmod 777 -R /home/pi/A108   
                        sudo chmod 777 -R /var/www/html
                         
                         