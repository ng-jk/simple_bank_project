# Server config
not allowed direct access, only index.php and image is allowed

# For frontend

## POST
when POST use complete sql query, but skip the prefix, like 
```SELECT user_name FROM user```
is actually fetch user name from table `system_user`

## GET
when GET use only status, secret, and query, query same as POST

## PUT, PATCH, DELETE
depend on handler exist or not, if not it will treat as POST

## NOT Http request
reject, you may build it yourself

## return data will be like
before DOM start
``` javascript
// this will load by backend before render frontend
<script>
    window.serverData = {
        query1: <?= json_encode($raw_data_from_database) ?>
        query2: <?= json_encode($raw_data_from_database) ?>
        query3: <?= json_encode($raw_data_from_database) ?>
        query4: <?= json_encode($raw_data_from_database) ?>
        metadata: <?= json_encode($metadata)?>
        // ...
    };
</script>

// here is your sample front end code
<script>
window.addEventListener('load', function() {

    /*
    Window.{
        serverData.{
            query1.{
                column_name => colum_value
            },
            ...
            metadata.{
                date_time => timestamp,
                query_result => [success||failed],
                query_error => [error message],
                login_status => [login||not_login],
                permission => ['user'||'admin'||'not_login']
            }
        }
    }
    */
    document.getElementById('hello_world').innerHTML = Window.serverData.query1.hello_key;
    document.getElementById('metadata').innerHTML = Window.serverData.metadata;
    console.log('Page loaded And Data from PHP:', data);

    // You can now use `data` here safely
});
</script>

<html>
    <head>
        <title>something like this so you can write javascript only for the whole project</title>
    </head>
    <body>
        <p id="hello_world"></p>
        <p id="metadata"></p>
    </body>
</html>

```
# For backend
similar to Codeigniter4 but without a framework, i may use composer to add package needed, dont change the vendor or any json wierd in the backend

## index.php
only contain middleware, include auth, check permission, and other

## router.php
only contain router, spefify with request type

## service folder
depend on service needed
add service php file into the folder

### service naming convention
service class naming convention is like:
> [category]_[interface|basic|not-fill]_service.php
class name is same as file name

service data class naming convention is like:
> [category].php
class name is same as file name

for example, for status service i will have
> status.php
> status_interface_service.php
> status_service.php
> status_basic_service.php

## controller folder
main logic of every request, resolve status logic

## migration folder
contain database migration file

## model folder
link to database, sanitize and validate data insert and retrieve

## function folder
only use trait