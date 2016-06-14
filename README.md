# db

## Standerd

```php
$db = new \Axelmedia\DB('mysql:host=localhost;dbname=testdb', 'user', 'password');
$db->query("SELECT DATABASE()")->fetchColumn();
```

## Singleton

```php
// at first
\Axelmedia\DB::createInstance('mysql://user:password@localhost/testdb');

// usage
$db = \Axelmedia\DB::getInstance();
$db->query("SELECT DATABASE()")->fetchColumn();
```
