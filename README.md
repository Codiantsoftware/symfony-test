
# Symfony Test 

  

This guide walks you through creating an authentication system in symfony.

  

# Table of Contents

[Pre-requisites](#Pre-requisites)

  

[Getting started](#Getting-started)
  

# Pre-requisites

- Composer 
- MySql database managed storage service
- PHP >= 8.0.2
  

# Getting-started

#### Clone the repository

```
git clone https://github.com/Codiantsoftware/symfony-test.git

```

#### Install Packages

```
composer install 

```

#### Create the database

Import database or run following command

```
php bin/console doctrine:database:create

php bin/console doctrine:migrations:migrate

```
#### Set Database Configuration

Configure .env file (rename .env.example to .env)

```
DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/db_name?serverVersion=5.7"

```
Example : DATABASE_URL="mysql://root:@127.0.0.1:3306/demo_app"

#### Run the Application

Run the below command

```
symfony server:start

- Navigate to `http://localhost:8080`

```

#### Link to Postman 

Import/Open Postman collection for API

https://www.postman.com/lunar-shuttle-536493/workspace/symfony-test/collection/31058465-2e76da66-cb20-4160-b591-ab162a389f0f
