# YADA - Deployment on Virtual Machines

In the README files for each tier ([web/README.md](../web/README.md) and [../api/README.md](api/README.md)) you can find additional information about YADA web and API components. In the following example you can find the simplest deployment of the YADA app using Azure virtual machines for the web and API tiers, and Azure SQL Database for the data tier.

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
vnet_name=yada
vnet_prefix=192.168.16.0/24
subnet_name=yada
subnet_prefix=192.168.16.0/28
nsg_name=yada-nsg
vm_size=Standard_B1s
web_vm_name=web
api_vm_name=api
publisher=kinvolk
offer=flatcar-container-linux-free
sku=stable-gen2
version=latest
api_image='erjosito/yadaapi:1.0'
web_image='erjosito/yadaweb:1.0'

# Create Resource Group and VNet
echo "Creating resource group and VNet..."
az group create -n $rg -l $location -o none
az network vnet create -g $rg -n $vnet_name --address-prefix $vnet_prefix -l $location -o none
az network vnet subnet create -g $rg --vnet-name $vnet_name -n $subnet_name --address-prefix $subnet_prefix -o none

# Create NSGs (you probably want to close this one down a notch)
echo "Creating NSGs..."
az network nsg create -n "$nsg_name" -g $rg -l $location -o none
az network nsg rule create -n YADAwebin --nsg-name "$nsg_name" -g $rg --priority 1000 --destination-port-ranges 80 --access Allow --protocol Tcp -o none
az network nsg rule create -n YADAapiin --nsg-name "$nsg_name" -g $rg --priority 1010 --destination-port-ranges 8080 --access Allow --protocol Tcp -o none
az network nsg rule create -n YADAsshin --nsg-name "$nsg_name" -g $rg --priority 1020 --destination-port-ranges 22 --access Allow --protocol Tcp -o none
az network vnet subnet update -n $subnet_name --vnet-name $vnet_name -g $rg --network-security-group $nsg_name -o none

# Create Azure SQL Server and database
echo "Creating Azure SQL..."
az sql server create -n $sql_server_name -g $rg -l $location --admin-user "$sql_username" --admin-password "$sql_password" -o none
az sql db create -n $sql_db_name -s $sql_server_name -g $rg -e Basic -c 5 --no-wait -o none
sql_server_fqdn=$(az sql server show -n $sql_server_name -g $rg -o tsv --query fullyQualifiedDomainName) && echo $sql_server_fqdn
```

## API

You can use any virtual machine with Docker support to run the YADA images. In this example we are using [Flatcar](https://www.flatcar.org/):

```bash
# Accept image terms
echo "Accepting image terms for $publisher:$offer:$sku..."
az vm image terms accept -p $publisher -f $offer --plan $sku -o none
# Cloud init file
api_cloudinit_file=/tmp/api_cloudinit.txt
cat <<EOF > $api_cloudinit_file
#!/bin/bash
docker run --restart always -d -p 8080:8080 -e "SQL_SERVER_FQDN=$sql_server_fqdn" -e "SQL_SERVER_USERNAME=$sql_username" -e "SQL_SERVER_PASSWORD=$sql_password" --name api $api_image
EOF
# Create API VM
echo "Creating API VM..."
start_time=`date +%s`
az vm create -n $api_vm_name -g $rg -l $location --image "${publisher}:${offer}:${sku}:${version}" --generate-ssh-keys --size $vm_size \
    --public-ip-address "${api_vm_name}-pip" --public-ip-sku Standard --vnet-name $vnet_name --nsg '' \
    --subnet $subnet_name --custom-data $api_cloudinit_file --security-type Standard -o none
run_time=$(expr `date +%s` - $start_time)
((minutes=${run_time}/60))
((seconds=${run_time}%60))
api_nic_id=$(az vm show -n $api_vm_name -g "$rg" --query 'networkProfile.networkInterfaces[0].id' -o tsv)
api_private_ip=$(az network nic show --ids $api_nic_id --query 'ipConfigurations[0].privateIpAddress' -o tsv)
api_public_ip=$(az network public-ip show -n "${api_vm_name}-pip" -g $rg --query ipAddress -o tsv)
echo "Virtual machine provisioned in $minutes minutes and $seconds seconds, private IP $api_private_ip and public IP $api_public_ip. Checking health now..."
# Update Azure SQL Server IP firewall with ACI container IP
curl -s "http://${api_public_ip}:8080/api/healthcheck"
echo "Adding public IP $api_public_ip to Azure SQL firewall rules..."
az sql server firewall-rule create -g "$rg" -s "$sql_server_name" -n public_api_aci-source --start-ip-address "$api_public_ip" --end-ip-address "$api_public_ip" -o none
```

## Web frontend

The web frontend can be created in a similar way:

```bash
# Cloud init file
web_cloudinit_file=/tmp/web_cloudinit.txt
cat <<EOF > $web_cloudinit_file
#!/bin/bash
docker run --restart always -d -p 80:80 -e "API_URL=http://${api_private_ip}:8080" --name web $web_image
EOF
# Create Web VM
echo "Creating Web VM..."
az vm create -n $web_vm_name -g $rg -l $location --image "${publisher}:${offer}:${sku}:${version}" --generate-ssh-keys --size $vm_size \
    --public-ip-address "${web_vm_name}-pip" --public-ip-sku Standard --vnet-name $vnet_name --nsg '' \
    --subnet $subnet_name --custom-data $web_cloudinit_file --security-type Standard -o none
web_public_ip=$(az network public-ip show -n "${web_vm_name}-pip" -g $rg --query ipAddress -o tsv)
# Finish
echo "You can now browse to http://$web_public_ip"
```

## Using Ubuntu image

The previous examples use a Flatcar image with Docker preinstalled. In certain situations it might be desirable using a more standard image, for example if the user has no rights to accept the terms for the Flatcar image. The downside will be a longer deployment time (since Docker needs to be installed):

```bash
# Ubuntu image data
offer="0001-com-ubuntu-server-focal"
publisher="Canonical"
sku="20_04-lts-gen2"
version=latest
# API cloud init file
apiubuntu_cloudinit_file=/tmp/apiubuntu_cloudinit.txt
cat <<EOF > $apiubuntu_cloudinit_file
#!/bin/bash
apt update
apt install -y apt-transport-https ca-certificates curl software-properties-common
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo apt-key add -
add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu focal stable"
apt-cache policy docker-ce
apt install -y docker-ce
docker run --restart always -d -p 8080:8080 -e "SQL_SERVER_FQDN=$sql_server_fqdn" -e "SQL_SERVER_USERNAME=$sql_username" -e "SQL_SERVER_PASSWORD=$sql_password" --name api $api_image
EOF
# Create API VM
echo "Creating API Ubuntu VM..."
start_time=`date +%s`
az vm create -n apiubuntu -g $rg -l $location --image "${publisher}:${offer}:${sku}:${version}" --generate-ssh-keys --size $vm_size \
    --public-ip-address apiubuntu-pip --public-ip-sku Standard --vnet-name $vnet_name --nsg '' \
    --subnet $subnet_name --custom-data $apiubuntu_cloudinit_file -o none
run_time=$(expr `date +%s` - $start_time)
((minutes=${run_time}/60))
((seconds=${run_time}%60))
apiubuntu_nic_id=$(az vm show -n apiubuntu -g "$rg" --query 'networkProfile.networkInterfaces[0].id' -o tsv)
apiubuntu_private_ip=$(az network nic show --ids $apiubuntu_nic_id --query 'ipConfigurations[0].privateIpAddress' -o tsv)
apiubuntu_public_ip=$(az network public-ip show -n apiubuntu-pip -g $rg --query ipAddress -o tsv)
echo "Virtual machine provisioned in $minutes minutes and $seconds seconds, private IP $apiubuntu_private_ip and public IP $apiubuntu_public_ip. Checking health now..."
curl -s "http://${apiubuntu_public_ip}:8080/api/healthcheck"
# Update Azure SQL Server IP firewall with ACI container IP
echo "Adding public IP $apiubuntu_public_ip to Azure SQL firewall rules..."
az sql server firewall-rule create -g "$rg" -s "$sql_server_name" -n public_api_aci-source --start-ip-address "$apiubuntu_public_ip" --end-ip-address "$apiubuntu_public_ip" -o none
# Web cloud init file
webubuntu_cloudinit_file=/tmp/webubuntu_cloudinit.txt
cat <<EOF > $webubuntu_cloudinit_file
#!/bin/bash
apt update
apt install -y apt-transport-https ca-certificates curl software-properties-common
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo apt-key add -
add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu focal stable"
apt-cache policy docker-ce
apt install -y docker-ce
docker run --restart always -d -p 80:80 -e "API_URL=http://${apiubuntu_private_ip}:8080" --name web $web_image
EOF
# Create Web VM
echo "Creating Web Ubuntu VM..."
start_time=`date +%s`
az vm create -n webubuntu -g $rg -l $location --image "${publisher}:${offer}:${sku}:${version}" --generate-ssh-keys --size $vm_size \
    --public-ip-address webubuntu-pip --public-ip-sku Standard --vnet-name $vnet_name --nsg '' \
    --subnet $subnet_name --custom-data $webubuntu_cloudinit_file -o none
run_time=$(expr `date +%s` - $start_time)
((minutes=${run_time}/60))
((seconds=${run_time}%60))
webubuntu_public_ip=$(az network public-ip show -n webubuntu-pip -g $rg --query ipAddress -o tsv)
# Finish
echo "Virtual machine provisioned in $minutes minutes and $seconds seconds, you can now browse to http://$webubuntu_public_ip"
```

Potentially cloud init syntax can be used instead of a bash script (see [https://stackoverflow.com/questions/24418815/how-do-i-install-docker-using-cloud-init](https://stackoverflow.com/questions/24418815/how-do-i-install-docker-using-cloud-init) for other variations, such as a simplified install using the default docker package in the Ubuntu repositories):

```shell
#cloud-config
apt:
  sources:
    docker.list:
      source: deb [arch=amd64] https://download.docker.com/linux/ubuntu $RELEASE stable
      keyid: 9DC858229FC7DD38854AE2D88D81803C0EBFCD88
packages:
  - docker-ce
  - docker-ce-cli
runcmd:
  - docker run --restart always -d -p 80:80 -e "API_URL=http://${apiubuntu_private_ip}:8080" --name web $web_image
```

or

```shell
#cloud-config
packages:
  - docker.io
# create the docker group
groups:
  - docker
# Add default auto created user to docker group
system_info:
  default_user:
    groups: [docker]
runcmd:
  - docker run --restart always -d -p 80:80 -e "API_URL=http://${apiubuntu_private_ip}:8080" --name web $web_image
```
