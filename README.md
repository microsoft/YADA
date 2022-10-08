# Yet Another Demo App

This repo contains code for a 3-tier application that can be used to explore workload platforms. Other demo or helloworld apps just show a fancy page, potentially even with some functionality like To Do lists, voting options or even simulating enterprise apps. YADA (Yet Another Demo App) doesn't pretend to be a real application, but instead it will give you diagnostics information that will help you understand the underlying infrastructure, such as:

- IP address (private and public)
- IP address with which the database sees the app tier
- HTTP request headers and cookies
- Instance MetaData Service (IMDS)
- DNS and reverse resolution
- Access between tiers
- Outbound connectivity with curl
- Drive up CPU load to test autoscaling
- Storage benchnmarking

YADA is composed of a web tier and a REST-based application tier that will access a database. The database can be SQL Server, MySQL or Postgres, and no special databases need to be created:

![Application architecture](web/app_arch.orig.png)

Both the web tier and the application tier will give information about the platform where they are running (hostname, public IP address, IMDS and more). The web tier is customizable with different brandings and background colors. With the default branding and cyan background it looks like this:

![Web tier](./web/homepage_screenshot.png)

Both app and web tiers are containerized and can be deployed in different platforms: Virtual machines with Docker (such as [Flatcar](https://www.flatcar.org/)), Kubernetes, Azure Container Instances, Azure Web Apps or any other container-based architecture.

You can find the images for the web and API tier in these public images in Dockerhub:

- erjosito/yadaweb:1.0
- erjosito/yadaapi:1.0

## Deployment on Azure Container Instances

In the README files for each tier ([web/README.md](web/README.md) and [api/README.md](api/README.md)) you can find additional instructions for deployment in multiple Azure platforms. In the following example you can find the simplest deployment of all three tiers using Azure Container Instances for the web and API tiers, and Azure SQL Database for the data tier:

```bash
# Variables
rg=rg$RANDOM
location=eastus
sql_server_name=sqlserver$RANDOM
sql_db_name=mydb
sql_username=azure
sql_password=$(openssl rand -base64 10)  # 10-character random password

# Create Resource Group
az group create -n $rg -l $location

# Create Azure SQL Server
az sql server create -n $sql_server_name -g $rg -l $location --admin-user "$sql_username" --admin-password "$sql_password"
az sql db create -n $sql_db_name -s $sql_server_name -g $rg -e Basic -c 5 --no-wait
sql_server_fqdn=$(az sql server show -n $sql_server_name -g $rg -o tsv --query fullyQualifiedDomainName) && echo $sql_server_fqdn

# Create ACI for API tier
az container create -n api -g $rg \
    -e "SQL_SERVER_USERNAME=$sql_username" "SQL_SERVER_PASSWORD=$sql_password" "SQL_SERVER_FQDN=$sql_server_fqdn" \
    --image erjosito/yadaapi:1.0 --ip-address public --ports 8080
api_pip_address=$(az container show -n api -g $rg --query ipAddress.ip -o tsv)
healthcheck=$(curl -s "http://${api_pip_address}:8080/api/healthcheck" | jq -r .health)
if [[ "$healthcheck" == "OK" ]]; then
    api_outbound_pip=$(curl -s "http://${api_pip_address}:8080/api/ip" | jq -r .my_public_ip)
    az sql server firewall-rule create -g $rg -s $sql_server_name -n yadaapi --start-ip-address $api_outbound_pip --end-ip-address $api_outbound_pip
fi

# Create ACI for web tier with cyan background and WTH branding. Other colors you can use: #92cb96 (green), #fcba87 (orange), #fdfbc0 (yellow)
az container create -n web -g $rg -e "API_URL=http://${api_pip_address}:8080" "BACKGROUND=#aaf1f2"  "BRANDING=whatthehack" \
    --image erjosito/yadaweb:1.0 --ip-address public --ports 80
web_pip_address=$(az container show -n web -g $rg --query ipAddress.ip -o tsv)

# Finish
echo "You can point your browser to http://${web_pip_address}"
```

## Contributing

This project welcomes contributions and suggestions.  Most contributions require you to agree to a
Contributor License Agreement (CLA) declaring that you have the right to, and actually do, grant us
the rights to use your contribution. For details, visit https://cla.opensource.microsoft.com.

When you submit a pull request, a CLA bot will automatically determine whether you need to provide
a CLA and decorate the PR appropriately (e.g., status check, comment). Simply follow the instructions
provided by the bot. You will only need to do this once across all repos using our CLA.

This project has adopted the [Microsoft Open Source Code of Conduct](https://opensource.microsoft.com/codeofconduct/).
For more information see the [Code of Conduct FAQ](https://opensource.microsoft.com/codeofconduct/faq/) or
contact [opencode@microsoft.com](mailto:opencode@microsoft.com) with any additional questions or comments.

## Trademarks

This project may contain trademarks or logos for projects, products, or services. Authorized use of Microsoft trademarks or logos is subject to and must follow [Microsoft's Trademark & Brand Guidelines](https://www.microsoft.com/en-us/legal/intellectualproperty/trademarks/usage/general).
Use of Microsoft trademarks or logos in modified versions of this project must not cause confusion or imply Microsoft sponsorship.
Any use of third-party trademarks or logos are subject to those third-party's policies.
