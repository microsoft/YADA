# YADA - Deployment on Azure App Services

In the README files for each tier ([web/README.md](../web/README.md) and [../api/README.md](api/README.md)) you can find additional information about YADA web and API components. In the following example you can find the simplest deployment of the YADA app using Azure Web Apps for the web and API tiers, and Azure SQL Database for the data tier.

If you don't have a database, you can deploy one using SQL Server:

```bash
# Variables
random_suffix=$RANDOM
rg=rg$random_suffix
location=eastus
sql_server_name=sqlserver$random_suffix
sql_db_name=mydb
sql_username=azure
sql_password=$(openssl rand -base64 10)  # 10-character random password
api_image='erjosito/yadaapi:1.0'
web_image='erjosito/yadaweb:1.0'
web_image='securenetworkingopenhack/ohndweb:webapp'

# Create Resource Group
echo "Creating resource group..."
az group create -n $rg -l $location -o none

# Create Azure SQL Server and database
echo "Creating Azure SQL..."
az sql server create -n $sql_server_name -g $rg -l $location --admin-user "$sql_username" --admin-password "$sql_password" -o none
az sql db create -n $sql_db_name -s $sql_server_name -g $rg -e Basic -c 5 --no-wait -o none
sql_server_fqdn=$(az sql server show -n $sql_server_name -g $rg -o tsv --query fullyQualifiedDomainName) && echo $sql_server_fqdn
```

## Run the API on Azure App Services

This example Azure CLI code deploys the API image on Azure Application Services (aka Web App):

```bash
# Run API on Web App
svcplan_name=webappplan
svcplan_sku=B1
app_name_api=api-$random_suffix
echo "Creating webapp for API..."
az appservice plan create -n $svcplan_name -g $rg --sku $svcplan_sku --is-linux -o none
az webapp create -n $app_name_api -g $rg -p $svcplan_name --deployment-container-image-name $api_image -o none
az webapp config appsettings set -n $app_name_api -g $rg --settings "WEBSITES_PORT=8080" "SQL_SERVER_USERNAME=$sql_username" "SQL_SERVER_PASSWORD=$sql_password" "SQL_SERVER_FQDN=${sql_server_fqdn}" -o none
az webapp restart -n $app_name_api -g $rg -o none
app_url_api=$(az webapp show -n $app_name_api -g $rg --query defaultHostName -o tsv) && echo $app_url_api
```

## Update Azure SQL firewall

You can either use the `api/ip` endpoint of the application to find out the API's egress IP address, or the webapp API:

```bash
# Update Azure SQL Server IP firewall with ACI container IP
api_egress_ip=$(curl -s "http://${app_url_api}/api/ip" | jq -r .my_public_ip)
az sql server firewall-rule create -g "$rg" -s "$sql_server_name" -n public_api_aci-source --start-ip-address "$api_egress_ip" --end-ip-address "$api_egress_ip"
```

## Run the web frontend on Azure App Services

Now you can deploy the web image:

```bash
# Run on Web App
app_name_web=web-$random_suffix
echo "Creating webapp for frontend..."
az webapp create -n $app_name_web -g $rg -p $svcplan_name --deployment-container-image-name $web_image -o none
az webapp config appsettings set -n $app_name_web -g $rg --settings "API_URL=https://${app_url_api}" -o none
az webapp restart -n $app_name_web -g $rg -o none
app_url_web=$(az webapp show -n $app_name_web -g $rg --query defaultHostName -o tsv) && echo $app_url_web
```
