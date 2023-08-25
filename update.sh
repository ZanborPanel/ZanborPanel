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

colorized_echo green "\n[+] - Please wait for a few seconde, the bee panel robot is being installed. . ."

echo " "

question="Please select your action?"
actions=("Update Bot", "Delete Bot", "Donate", "Exit")

select action in "${actions[@]}"
do
    case $action in "Update Bot")
            echo -e "\n"
            read -p "Are you sure you want to update? [y/n] : " answer
            if [ "$answer" != "${answer#[Yy]}" ]; then
                colorized_echo green "Please wait, Updating . . ."
                sudo apt install curl -y
                sllep 2
                mv /var/www/html/ZanborPanelBot/install/zanbor.install /var/www/html/zanbor.install
                rm -r /var/www/html/ZanborPanelBot/
                git clone https://github.com/ZanborPanel/ZanborPanel.git /var/www/html/ZanborPanelBot/
                sudo chmod -R 777 /var/www/html/ZanborPanelBot/
                mv /var/www/html/zanbor.install /var/www/html/ZanborPanelBot/install/zanbor.install
                sleep 2

                token=$(grep -oP '(?<="token": ")[^"]*' /var/www/html/ZanborPanelBot/install/zanbor.install)
                dev=$(grep -oP '(?<="dev": ")[^"]*' /var/www/html/ZanborPanelBot/install/zanbor.install)
                db_name=$(grep -oP '(?<="db_name": ")[^"]*' /var/www/html/ZanborPanelBot/install/zanbor.install)
                db_username=$(grep -oP '(?<="db_username": ")[^"]*' /var/www/html/ZanborPanelBot/install/zanbor.install)
                db_password=$(grep -oP '(?<="db_password": ")[^"]*' /var/www/html/ZanborPanelBot/install/zanbor.install)

                source_file="/var/www/html/ZanborPanelBot/config.php"
                destination_file="/var/www/html/ZanborPanelBot/config.php.tmp"
                replace=$(cat "$source_file" | sed -e "s/\[\*TOKEN\*\]/${token}/g" -e "s/\[\*DEV\*\]/${dev}/g" -e "s/\[\*DB-NAME\*\]/${db_name}/g" -e "s/\[\*DB-USER\*\]/${db_username}/g" -e "s/\[\*DB-PASS\*\]/${db_password}/g")
                echo "$replace" > "$destination_file"
                mv "$destination_file" "$source_file"

                sleep 2

                TEXT_MESSAGE="🔄 The ZanborPanel Bot Has Been Successfully Updated -> @ZanborPanel | @ZanborPanelGap"
                curl -s -X POST "https://api.telegram.org/bot${TOKEN}/sendMessage" -d chat_id="${CHAT_ID}" -d text="${TEXT_MESSAGE}"

                sleep 2
                clear
                colorized_echo green "[+] The ZanborPanel Bot Has Been Successfully Updated"
                colorized_echo green "[+] Telegram channel: @ZanborPanel || Telegram group: @ZanborPanelGap"

            else
                clear
                colorized_echo red "Update Canceled !"
                exit 1
            fi

        break;;
        "Delete Bot")
            echo -e "\n"
            read -p "Are you sure you want to update? [y/n] : " answer
            if [ "$answer" != "${answer#[Yy]}" ]; then
                colorized_echo green "Please wait, Deleting . . ."
                rm -r /var/www/html/ZanborPanelBot/

                sleep 2

                TEXT_MESSAGE="❌ The ZanborPanel Bot Has Been Successfully Deleted -> @ZanborPanel | @ZanborPanelGap"
                curl -s -X POST "https://api.telegram.org/bot${TOKEN}/sendMessage" -d chat_id="${CHAT_ID}" -d text="${TEXT_MESSAGE}"

                sleep 2
                clear
                colorized_echo green "[+] The ZanborPanel Bot Has Been Successfully Deleted"
                colorized_echo green "[+] Telegram channel: @ZanborPanel || Telegram group: @ZanborPanelGap"

            else
                clear
                colorized_echo red "Delete Canceled !"
                exit 1
            fi
        break;;
        "Donate")
            echo -e "\n"
            colorized_echo green "[+] Bank Meli: 6037998195739130\n\n[+] Tron (TRX): TAwNcAYrHp2SxhchywA6NhUEF4aVwJHufD\n\n[+] ETH, BNB, MATIC network (ERC20, BEP20): 0x36c5109885b59Ddd0cA4ea497765d326eb48396F\n\n[+] Bitcoin network: bc1qccunjllf2guca7dhwyw2x3u80yh4k0hg88yvpk" 
            exit 0
        break;;
        "Exit")
            echo -e "\n"
            colorized_echo green "Exited !"
        break;;
    esac
done

