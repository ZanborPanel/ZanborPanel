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

colorized_echo green "\n[+] - Please wait for a few seconde !"

echo " "

question="Please select your action?"
actions=("Update Bot" "Delete Bot" "Donate" "Exit")

select opt in "${actions[@]}"
do
    case $opt in 
        "Update Bot")
            echo -e "\n"
            read -p "Are you sure you want to update? [y/n] : " answer
            if [ "$answer" != "${answer#[Yy]}" ]; then
                colorized_echo green "Please wait, Updating . . ."
                sudo apt install curl -y
                sleep 2
                mv /var/www/html/ZanborPanelBot/install/zanbor.install /var/www/html/zanbor.install
                sleep 1
                rm -r /var/www/html/ZanborPanelBot/
                colorized_echo green "All file and folder deleted for update bot !\n"
                git clone https://github.com/ZanborPanel/ZanborPanel.git /var/www/html/ZanborPanelBot/
                sudo chmod -R 777 /var/www/html/ZanborPanelBot/
                mv /var/www/html/zanbor.install /var/www/html/ZanborPanelBot/install/zanbor.install
                sleep 2

                cat /var/www/html/ZanborPanelBot/install/zanbor.install
                content=$(cat /var/www/html/ZanborPanelBot/install/zanbor.install)
                token=$(echo "$content" | jq -r '.token')
                dev=$(grep -oP '(?<="dev": ")[^"]*' /var/www/html/ZanborPanelBot/install/zanbor.install)
                db_name=$(grep -oP '(?<="db_name": ")[^"]*' /var/www/html/ZanborPanelBot/install/zanbor.install)
                db_username=$(grep -oP '(?<="db_username": ")[^"]*' /var/www/html/ZanborPanelBot/install/zanbor.install)
                db_password=$(grep -oP '(?<="db_password": ")[^"]*' /var/www/html/ZanborPanelBot/install/zanbor.install)
                echo -e "Your Bot Token: ${token}"

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
                echo -e "\n\n"
                colorized_echo green "[+] The ZanborPanel Bot Has Been Successfully Updated"
                colorized_echo green "[+] Telegram channel: @ZanborPanel || Telegram group: @ZanborPanelGap"
                echo -e "\n"

            else
                echo -e "\n"
                colorized_echo red "Update Canceled !"
                echo -e "\n"
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
                echo -e "\n"
                colorized_echo green "[+] The ZanborPanel Bot Has Been Successfully Deleted"
                colorized_echo green "[+] Telegram channel: @ZanborPanel || Telegram group: @ZanborPanelGap"
                echo -e "\n"

            else
                echo -e "\n"
                colorized_echo red "Delete Canceled !"
                echo -e "\n"
                exit 1
            fi

            break;;
        "Donate")
            echo -e "\n"
            colorized_echo green "[+] Bank Meli: 6037998195739130\n\n[+] Tron (TRX): TAwNcAYrHp2SxhchywA6NhUEF4aVwJHufD\n\n[+] ETH, BNB, MATIC network (ERC20, BEP20): 0x36c5109885b59Ddd0cA4ea497765d326eb48396F\n\n[+] Bitcoin network: bc1qccunjllf2guca7dhwyw2x3u80yh4k0hg88yvpk" 
            echo -e "\n"
            exit 0

            break;;
        "Exit")
            echo -e "\n"
            colorized_echo green "Exited !"
            echo -e "\n"

            break;;
            *) echo "Invalid option!"
    esac
done
