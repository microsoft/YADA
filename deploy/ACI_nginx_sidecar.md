# YADA - Deployment on ACI with an nginx sidecar

You might have noticed that neither the web or app component of YADA provide TLS, but you can fix that with a sidecar container. You can use a YAML-based deployment to include two containers into the group: one with the web or API, and another one with nginx providing TLS. The following example shows this concept for the API container:

```bash
# Create cert
openssl req -new -newkey rsa:2048 -nodes -keyout /tmp/ssl.key -out /tmp/ssl.csr -subj "/C=US/ST=US/O=Self Signed/CN=Self Signed Cert"
openssl x509 -req -days 365 -in /tmp/ssl.csr -signkey /tmp/ssl.key -out /tmp/ssl.crt
ssl_crt=$(cat /tmp/ssl.crt | base64)
ssl_key=$(cat /tmp/ssl.key | base64)
# Create nginx conf
cat <<EOF > /tmp/nginx.conf
user nginx;
worker_processes auto;
events {
  worker_connections 1024;
}
pid        /var/run/nginx.pid;
http {
    server {
        listen 443 ssl;
        server_name localhost;
        ssl_protocols              TLSv1.2;
        ssl_ciphers                ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:ECDHE-RSA-RC4-SHA:ECDHE-ECDSA-RC4-SHA:AES128:AES256:RC4-SHA:HIGH:!aNULL:!eNULL:!EXPORT:!DES:!3DES:!MD5:!PSK;
        ssl_prefer_server_ciphers  on;
        ssl_session_cache    shared:SSL:10m; # a 1mb cache can hold about 4000 sessions, so we can hold 40000 sessions
        ssl_session_timeout  24h;
        keepalive_timeout 300; # up from 75 secs default
        add_header Strict-Transport-Security 'max-age=31536000; includeSubDomains';
        ssl_certificate      /etc/nginx/ssl.crt;
        ssl_certificate_key  /etc/nginx/ssl.key;
        location /api/ {
            proxy_pass http://127.0.0.1:8080 ;
            proxy_set_header Connection "";
            proxy_set_header Host \$host;
            proxy_set_header X-Real-IP \$remote_addr;
            proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
            # proxy_set_header X-Forwarded-For \$remote_addr;
        }
    }
}
EOF
nginx_conf=$(cat /tmp/nginx.conf | base64)
# Create ACI deployment file
cat <<EOF > /tmp/deploy-aci.yaml
api-version: 2019-12-01
location: $location
name: api
properties:
  containers:
  - name: nginx
    properties:
      image: mcr.microsoft.com/oss/nginx/nginx:1.15.5-alpine
      ports:
      - port: 443
        protocol: TCP
      resources:
        requests:
          cpu: 1.0
          memoryInGB: 1.5
      volumeMounts:
      - name: nginx-config
        mountPath: /etc/nginx
  - name: api
    properties:
      image: erjosito/yadaapi:1.0
      environmentVariables:
        - name: 'SQL_SERVER_FQDN'
          value: '$sql_server_fqdn'
        - name: 'SQL_SERVER_USER'
          value: '$sql_username'
        - name: 'SQL_SERVER_PASSWORD'
          secureValue: '$sql_password'
      ports:
      - port: 8080
        protocol: TCP
      resources:
        requests:
          cpu: 0.5
          memoryInGB: 0.5
  volumes:
  - secret:
      ssl.crt: "$ssl_crt"
      ssl.key: "$ssl_key"
      nginx.conf: "$nginx_conf"
    name: nginx-config
  ipAddress:
    ports:
    - port: 443
      protocol: TCP
    type: Public
  osType: Linux
tags: null
type: Microsoft.ContainerInstance/containerGroups
EOF
# Deploy container group
az container create -g $rg --file /tmp/deploy-aci.yaml -o none
```
