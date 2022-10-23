# YADA - Deployment on Azure Container Instances

In the README files for each tier ([web/README.md](../web/README.md) and [../api/README.md](api/README.md)) you can find additional information about YADA web and API components. In the following example you can find the simplest deployment of the YADA app using Azure Container Instances with pulic IP addresses for the web and API tiers, and Azure SQL Database for the data tier:

```bash
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

# Create ACI for API tier
echo "Creating app ACI..."
az container create -n api -g $rg \
    -e "SQL_SERVER_USERNAME=$sql_username" "SQL_SERVER_PASSWORD=$sql_password" "SQL_SERVER_FQDN=$sql_server_fqdn" \
    --image erjosito/yadaapi:1.0 --ip-address public --ports 8080 -o none
api_pip_address=$(az container show -n api -g $rg --query ipAddress.ip -o tsv)
healthcheck=$(curl -s "http://${api_pip_address}:8080/api/healthcheck" | jq -r .health)
if [[ "$healthcheck" == "OK" ]]; then
    api_outbound_pip=$(curl -s "http://${api_pip_address}:8080/api/ip" | jq -r .my_public_ip)
    echo "Adding $api_outbound_pip to Azure SQL firewall rules..."
    az sql server firewall-rule create -g $rg -s $sql_server_name -n yadaapi --start-ip-address $api_outbound_pip --end-ip-address $api_outbound_pip -o none
fi

# Create ACI for web tier with cyan background and WTH branding. Other colors you can use: #92cb96 (green), #fcba87 (orange), #fdfbc0 (yellow)
echo "Creating web ACI..."
az container create -n web -g $rg -e "API_URL=http://${api_pip_address}:8080" "BACKGROUND=#aaf1f2" "BRANDING=whatthehack" \
    --image erjosito/yadaweb:1.0 --ip-address public --ports 80 -o none
web_pip_address=$(az container show -n web -g $rg --query ipAddress.ip -o tsv)
```

And we are done!

```bash
# Finish
echo "You can point your browser to http://${web_pip_address}"
```

## Using Azure Container Registry

If running the image from your own Azure Container Registry, here you have an example of how to deploy the API component:

```bash
# Deploy API on ACI from ACR
acr_name=<your_ACR>
az acr update -n "$acr_name" --admin-enabled true -o none
acr_usr=$(az acr credential show -n "$acr_name" -g "$rg" --query 'username' -o tsv)
acr_pwd=$(az acr credential show -n "$acr_name" -g "$rg" --query 'passwords[0].value' -o tsv)
az container create -n api -g $rg  \
    -e "SQL_SERVER_USERNAME=${sql_username}" "SQL_SERVER_PASSWORD=${sql_password}" "SQL_SERVER_FQDN=${sql_server_fqdn}" \
    --image "${acr_name}.azurecr.io/yadaapi:1.0" --ip-address public --ports 8080 \
    --registry-username "$acr_usr" --registry-password "$acr_pwd" -o none
```

## Azure Key Vault integration

If testing access to Azure Key Vault, you might want to deploy the image in an ACI associated to a managed identity, and instead of supplying a database password you can provide a Key Vault and secret name:

```bash
# Deploy API on ACI with managed identity
rg=<your_resource_group>
akv_name=<your_azure_keyvault>
akv_secret_name=<your_db_password_akv_secret_name>
identity_name=myACIid
az identity create -n $identity_name -g $rg -o none
identity_spid=$(az identity show -g $rg -n $identity_name --query principalId -o tsv)
identity_appid=$(az identity show -g $rg -n $identity_name --query clientId -o tsv)
identity_id=$(az identity show -g $rg -n $identity_name --query id -o tsv)
az keyvault set-policy -n $akv_name -g $rg --object-id $identity_spid --secret-permissions get list -o none
az container create -n api -g $rg \
    -e "SQL_SERVER_USERNAME=$sql_username" "SQL_SERVER_FQDN=$sql_server_fqdn" "AKV_NAME=$akv_name" "AKV_SECRET_NAME=$akv_secret_name" \
    --image erjosito/yadaapi:1.0 --ip-address public --ports 8080 --assign-identity $identity_id -o none
```
