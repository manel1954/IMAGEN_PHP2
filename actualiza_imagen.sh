#!/bin/bash

                        cd /home/pi/IMAGEN_PHP                                             
                        git pull --force                      
                        sudo rm -R /home/pi/A108
                        mkdir /home/pi/A108                                                
                        cp -R /home/pi/IMAGEN_PHP/* /home/pi/A108
                        cp -R /home/pi/IMAGEN_PHP/html/ /var/www/
                        sleep 6                                             
                        sudo chmod 777 -R /home/pi/A108   
                        sudo chmod 777 -R /var/www/html               
                        sudo apt-get install -y php-zip && sudo systemctl restart apache2
                        
                 


                        
                    
