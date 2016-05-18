# afrostream-billings

#First goal is to keep tracking of recurly Subscriptions and more


# Tests

Quelques tests fonctionnels sont disponibles à l'aide de behat.

Pour faire tourner les test localement il faut mettre en place un vhost avec la configuration suivante (apache2):

```
<VirtualHost *:80>
        ServerName test.afrostream-billing.localhost
        ServerAdmin webmaster@localhost
        DocumentRoot /home/stephane/projects/afrostream-billings/src
	    SetEnv ENVIRONMENT test

        <Directory /home/stephane/projects/afrostream-billings/src>
                AllowOverride All
                DirectoryIndex index.php

                Options Indexes FollowSymLinks
                Require all granted

        </Directory>
        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined
        #Include conf-available/serve-cgi-bin.conf
</VirtualHost>
```

Pour lancer les tests il suffit de lancer la commande behat ci-dessous depuis la racine du projet

```shell
./bin/behat -c test/behat.yml
```