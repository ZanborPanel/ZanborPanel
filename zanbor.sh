#!/bin/bash

# Written By: ZanborPanel
# Channel: @ZanborPanel
# Group: @ZanborPanelGap

if [ "$(id -u)" -ne 0 ]; then
    echo -e "\033[33mPlease run as root\033[0m"
    exit
fi

wait 

colorized_echo() {
    local color=$1
    local text=$2
    
    case $color in
        "red")
        printf "\e[91m${text}\e[0m\n";;
        "green")
        printf "\e[92m${text}\e[0m\n";;
        "yellow")
        printf "\e[93m${text}\e[0m\n";;
        "blue")
        printf "\e[94m${text}\e[0m\n";;
        "magenta")
        printf "\e[95m${text}\e[0m\n";;
        "cyan")
        printf "\e[96m${text}\e[0m\n";;
        *)
            echo "${text}"
        ;;
    esac
}

colorized_echo green "\n[+] - Please wait for a few hours, the bee panel robot is being installed. . ."

# update proccess !
sudo apt update && apt upgrade -y
colorized_echo green "The server was successfully updated . . .\n"

# install packages !
PACKAGES=(
    mysql-server 
    libapache2-mod-php 
    lamp-server^ 
    php-mbstring 
    apache2 
    php-zip 
    php-gd 
    php-json 
    php-curl 
)

colorized_echo green " Installing the necessary packages. . ."

for i in "${PACKAGES[@]}"
    do
        dpkg -s $i &> /dev/null
        if [ $? -eq 0 ]; then
            colorized_echo yellow "Package $i is currently installed on your server!"
        else
            apt install $i -y
            if [ $? -ne 0 ]; then
                colorized_echo red "Package $i could not be installed."
                exit 1
            fi
        fi
    done

# install more !
echo 'phpmyadmin phpmyadmin/app-password-confirm password zanborpanel' | debconf-set-selections
echo 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2' | debconf-set-selections
echo 'phpmyadmin phpmyadmin/mysql/admin-pass password zanborpanel' | debconf-set-selections
echo 'phpmyadmin phpmyadmin/mysql/app-pass password zanborpanel' | debconf-set-selections
echo 'phpmyadmin phpmyadmin/dbconfig-install boolean true' | debconf-set-selections
sudo apt-get install phpmyadmin -y
sudo ln -s /etc/phpmyadmin/apache.conf /etc/apache2/conf-available/phpmyadmin.conf
sudo a2enconf phpmyadmin.conf
sudo systemctl restart apache2

wait

sudo apt-get install -y php-soap
sudo apt-get install libapache2-mod-php

# service proccessing !
sudo systemctl enable mysql.service
sudo systemctl start mysql.service
sudo systemctl enable apache2
sudo systemctl start apache2

ufw allow 'Apache Full'
sudo systemctl restart apache2

colorized_echo green "Installing Zanbor . . ."

sleep 2

sudo apt install sshpass
sudo apt-get install pwgen
sudo apt-get install -y git
sudo apt-get install -y wget
sudo apt-get install -y unzip
sudo apt install curl -y
sudo apt-get install -y php-ssh2
sudo apt-get install -y libssh2-1-dev libssh2-1

sudo systemctl restart apache2.service

wait

git clone https://github.com/ZanborPanel/ZanborPanel.git /var/www/html/ZanborPanelBot
sudo chmod -R 777 /var/www/html/ZanborPanelBot/
colorized_echo green "\n\tAll ZanborPanel robot files/folders have been successfully installed on your server!"

wait

clear
echo -e " \n"

read -p "[+] Enter The Domain without [http:// | https://]: " domain
if [ "$domain" = "" ]; then
    colorized_echo green "Ok, continue . . ."
    colorized_echo green "Please wait !"
    sleep 2
else
    DOMAIN="$domain"
fi

sudo ufw allow 80
sudo ufw allow 443 
sudo apt install letsencrypt -y
sudo apt-get -y install certbot python3-certbot-apache
sudo systemctl enable certbot.timer
sudo certbot certonly --standalone --agree-tos --preferred-challenges http -d $DOMAIN
sudo certbot --apache --agree-tos --preferred-challenges http -d $DOMAIN

wait
clear
echo -e " \n"

wait

read -p "[+] Enter the [root (MySql)] user passord: " ROOT_PASSWORD
randdbpass=$(openssl rand -base64 8 | tr -dc 'a-zA-Z0-9' | head -c 10)
randdbdb=$(pwgen -A 8 1)
randdbname=$(openssl rand -base64 8 | tr -dc 'a-zA-Z0-9' | head -c 4)
dbname="ZanborPanel_${randdbpass}"

colorized_echo green "Please enter the database username (For Default -> Enter) :"
printf "[+] Default username is [${randdbdb}] :"
read dbuser
if [ "$dbuser" = "" ]; then
    dbuser=$randdbdb
else
    dbuser=$dbuser
fi

colorized_echo green "Please enter the database password (For Default -> Enter) :"
printf "[+] Default password is [${randdbpass}] :"
read dbpass
if [ "$dbpass" = "" ]; then
    dbpass=$randdbpass
else
    dbpass=$dbpass
fi

sshpass -p $ROOT_PASSWORD mysql -u root -p -e "SET GLOBAL validate_password.policy = LOW;"
sshpass -p $ROOT_PASSWORD mysql -u root -p -e "CREATE DATABASE $dbname;" -e "CREATE USER '$dbuser'@'%' IDENTIFIED WITH mysql_native_password BY '$dbpass';GRANT ALL PRIVILEGES ON * . * TO '$dbuser'@'%';FLUSH PRIVILEGES;" -e "CREATE USER '$dbuser'@'localhost' IDENTIFIED WITH mysql_native_password BY '$dbpass';GRANT ALL PRIVILEGES ON * . * TO '$dbuser'@'localhost';FLUSH PRIVILEGES;"
sshpass -p $ROOT_PASSWORD mysql -u root -p -e "GRANT ALL PRIVILEGES ON *.* TO 'phpmyadmin'@'localhost' WITH GRANT OPTION;"

colorized_echo green "[+] The robot database was created successfully!"

wait

# get bot and user information !
printf "\n\e[33m[+] \e[36mBot Token: \033[0m"
read TOKEN
printf "\e[33m[+] \e[36mChat id: \033[0m"
read CHAT_ID
printf "\e[33m[+] \e[36mEnter The Domain Without [https:// | http://]: \033[0m"
read DOMAIN
echo " "

if [ 'http' in "$DOMAIN" ]; then
    colorized_echo red "Input invalid !"
    exit 1
fi

if [ "$TOKEN" = "" ] || [ "$DOMAIN" = "" ] || [ "$CHAT_ID" = "" ]; then
    colorized_echo red "Input invalid !"
    exit 1
fi

wait
sleep 2

config_address="/var/www/html/ZanborPanelBot/install/zanbor.install"

if [ -f "$config_address" ]; then
    rm "$config_address"
fi

clear
echo -e "\n"
colorized_echo green "[+] Please wait . . .\n"
sleep 1

# add information to file
# touch('/var/www/html/ZanborPanelBot/install/zanbor.install')
echo "{\"development\":\"@ZanborPanel\",\"install_location\":\"server\",\"main_domin\":\"${DOMAIN}\",\"token\":\"${TOKEN}\",\"dev\":\"${CHAT_ID}\",\"db_name\":\"${dbname}\",\"db_username\":\"${randdbdb}\",\"db_password\":\"${randdbpass}\"}" > /var/www/html/ZanborPanelBot/install/zanbor.install

source_file="/var/www/html/ZanborPanelBot/config.php"
destination_file="/var/www/html/ZanborPanelBot/config.php.tmp"
replace=$(cat "$source_file" | sed -e "s/\[\*TOKEN\*\]/${TOKEN}/g" -e "s/\[\*DEV\*\]/${CHAT_ID}/g" -e "s/\[\*DB-NAME\*\]/${dbname}/g" -e "s/\[\*DB-USER\*\]/${dbuser}/g" -e "s/\[\*DB-PASS\*\]/${dbpass}/g")
echo "$replace" > "$destination_file"
mv "$destination_file" "$source_file"

sleep 2

# curl process
colorized_echo blue "Database Status:"
curl --location "https://${DOMAIN}/ZanborPanelBot/sql/sql.php?db_password=${dbpass}&db_name=${dbname}&db_username=${dbuser}"

colorized_echo blue "\n\nSet Webhook Status:"
curl -F "url=https://${DOMAIN}/ZanborPanelBot/index.php" "https://api.telegram.org/bot${TOKEN}/setWebhook"

colorized_echo blue "\n\nSend Message Status:"
TEXT_MESSAGE="✅ The ZanborPanel Bot Has Been Successfully Installed !"
curl -s -X POST "https://api.telegram.org/bot${TOKEN}/sendMessage" -d chat_id="${CHAT_ID}" -d text="${TEXT_MESSAGE}"
echo -e "\n\n"

sleep 1
colorized_echo green "[+] The ZanborPanel Bot Has Been Successfully Installed"
colorized_echo green "[+] Telegram channel: @ZanborPanel || Telegram group: @ZanborPanelGap"
echo -e "\n"
