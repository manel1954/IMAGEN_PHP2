#!/bin/bash

sudo tee /etc/systemd/system/displaydriver.service << 'EOF'
[Unit]
Description=MMDVM Display-Driver for Nextion via MQTT
After=mosquitto.service mmdvmhost.service

[Service]
Type=simple
User=root
WorkingDirectory=/home/pi/Display-Driver
ExecStart=/home/pi/Display-Driver/DisplayDriver DisplayDriver.ini
Restart=always

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sleep 2
sudo systemctl enable displaydriver.service
sleep 2
sudo systemctl start displaydriver.service

