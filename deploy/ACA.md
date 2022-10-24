# YADA - Deployment on Azure Container Apps

In the README files for each tier ([web/README.md](../web/README.md) and [../api/README.md](api/README.md)) you can find additional information about YADA web and API components. In the following example you can find the simplest deployment of the YADA app using Azure Container Apps with public ingresses for the web and API tiers, and Azure SQL Database for the data tier:

```bash
# Verify that containerapp extension is installed
az extension add -n containerapp

# Variables
rg=rg$RANDOM
location=eastus
sql_server_name=sqlserver$RANDOM
sql_db_name=mydb
sql_username=azure
sql_password=$(openssl rand -base64 10)  # 10-character random password

# Create Resource Group
echo "Creating resource group..."
az group create -n $rg -l $location -o none

# Create Azure SQL Server
echo "Creating Azure SQL..."
az sql server create -n $sql_server_name -g $rg -l $location --admin-user "$sql_username" --admin-password "$sql_password" -o none
az sql db create -n $sql_db_name -s $sql_server_name -g $rg -e Basic -c 5 --no-wait -o none
sql_server_fqdn=$(az sql server show -n $sql_server_name -g $rg -o tsv --query fullyQualifiedDomainName) && echo $sql_server_fqdn

# Create ACA environment
echo "Creating container apps environment..."
az containerapp env create -n yada -g $rg -l $location -o none
env_status=$(az containerapp env show -n yada -g $rg -o json --query 'properties.provisioningState' -o tsv)
while [[ "$env_status" != "Succeeded" ]]; do
  echo "Waiting for container app environment to be ready..."
  sleep 10
  env_status=$(az containerapp env show -n yada -g $rg -o json --query 'properties.provisioningState' -o tsv)
done

# Create ACA for API container
echo "Creating container app for the API container..."
az containerapp create -n api -g $rg \
    --image erjosito/yadaapi:1.0 --environment yada \
    --ingress external --target-port 8080 \
    --env-vars "SQL_SERVER_USERNAME=$sql_username" "SQL_SERVER_PASSWORD=$sql_password" "SQL_SERVER_FQDN=$sql_server_fqdn" -o none
api_fqdn=$(az containerapp show -n api -g $rg --query properties.configuration.ingress.fqdn -o tsv)
healthcheck=$(curl -s "https://${api_fqdn}/api/healthcheck" | jq '.health')
if [[ "$healthcheck" == "OK" ]]; then
    api_outbound_pip=$(curl -s "https://${api_fqdn}/api/ip" | jq -r .my_public_ip)
    echo "Adding $api_outbound_pip to Azure SQL firewall rules..."
    az sql server firewall-rule create -g $rg -s $sql_server_name -n yadaapi --start-ip-address $api_outbound_pip --end-ip-address $api_outbound_pip -o none
fi

# Create ACA for web tier with cyan background and WTH branding. Other colors you can use: #92cb96 (green), #fcba87 (orange), #fdfbc0 (yellow)
echo "Creating container app for the web container..."
az containerapp create -n web -g $rg --environment yada --env-vars "API_URL=https://${api_fqdn}" "BACKGROUND=#aaf1f2" "BRANDING=whatthehack" \
    --image erjosito/yadaweb:1.0 --ingress external --target-port 80 -o none
web_fqdn=$(az containerapp show -n web -g $rg --query properties.configuration.ingress.fqdn -o tsv)
```

And we are done!

```bash
# Finish
echo "You can point your browser to https://${web_fqdn}"
```

## Creating an internal API container

If you don't want to expose the API externally you can keep the API container internal (to the Container Apps environment) and deploy it with an internal ingress:

```bash
# Create internal ACA for API container
echo "Creating internal container app for the API container..."
az containerapp create -n api -g $rg \
    --image erjosito/yadaapi:1.0 --environment yada \
    --ingress internal --target-port 8080 \
    --env-vars "SQL_SERVER_USERNAME=$sql_username" "SQL_SERVER_PASSWORD=$sql_password" "SQL_SERVER_FQDN=$sql_server_fqdn" -o none
api_fqdn=$(az containerapp show -n api -g $rg --query properties.configuration.ingress.fqdn -o tsv)
api_outbound_pip=$(az containerapp show -n api -g $rg --query 'properties.outboundIpAddresses' -o tsv)
echo "Adding $api_outbound_pip to Azure SQL firewall rules..."
az sql server firewall-rule create -g $rg -s $sql_server_name -n yadaapi --start-ip-address $api_outbound_pip --end-ip-address $api_outbound_pip -o none
```

If you have already deployed the web container you need to redeploy it to update it with the new internal fqdn address:

```bash
echo "Updating container app for the web container..."
az containerapp create -n web -g $rg --environment yada --env-vars "API_URL=https://${api_fqdn}" "BACKGROUND=#aaf1f2" "BRANDING=whatthehack" \
    --image erjosito/yadaweb:1.0 --ingress external --target-port 80 -o none
web_fqdn=$(az containerapp show -n web -g $rg --query properties.configuration.ingress.fqdn -o tsv)
```

## Using Azure Container Registry

If running the image from your own Azure Container Registry, here you have an example of how to deploy the API component:

```bash
# Deploy API on ACA from ACR
acr_name=<your_ACR>
az acr update -n "$acr_name" --admin-enabled true -o none
acr_usr=$(az acr credential show -n "$acr_name" -g "$rg" --query 'username' -o tsv)
acr_pwd=$(az acr credential show -n "$acr_name" -g "$rg" --query 'passwords[0].value' -o tsv)
az containerapp create -n api -g $rg --environment yada  \
    --env-vars "SQL_SERVER_USERNAME=${sql_username}" "SQL_SERVER_PASSWORD=${sql_password}" "SQL_SERVER_FQDN=${sql_server_fqdn}" \
    --image "${acr_name}.azurecr.io/yadaapi:1.0" --ingress external --target-port 8080 \
    --registry-server "${acr_name}.azurecr.io" --registry-username "$acr_usr" --registry-password "$acr_pwd" -o none
```
