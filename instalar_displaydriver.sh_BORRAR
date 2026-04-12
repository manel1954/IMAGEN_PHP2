#!/bin/bash

ROJO="\033[1;31m"
VERDE="\033[1;32m"
AMARILLO="\033[1;33m"
GRIS="\033[0m"

if [ -d "/home/pi/A108" ]; then
        cd /home/pi/A108
        sudo sh servicio_displaydriver.sh
    else
        echo "No existe /home/pi/A108"
    fi

sudo chmod 777 /etc/systemd/system/displaydriver.service

set -e
if [ -d "/home/pi/Display-Driver" ]; then
    #echo -e "${VERDE}"
    echo "********************************************************"
    echo "         Display-Driver ya ha sido instalado."
    echo "********************************************************"

else
    cd /home/pi
    git clone https://github.com/g4klx/Display-Driver.git

    sleep 2
    cd /home/pi/Display-Driver
    make
    sudo make install

    sudo chmod 777 -R /home/pi/Display-Driver
    
    sudo apt-get install -y php-zip
    sudo systemctl restart apache2

    
    clear
fi
