
# For frontend

## POST
when POST use complete sql query, but skip the prefix, like 
```SELECT user_name FROM user```
is actually fetch user name from table `system_user`

## GET
when GET use only status and secret, status is current status

## HEAD
when HEAD request will treat as status=HEAD

## PUT, PATCH, DELETE
depend on handler exist or not, if not it will treat as POST

## NOT Http request
reject, otherwise you may build it yourself

when other 