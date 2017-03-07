# Simple ORM
This is just a simple ORM project for college use. (coded in like 2 hours so not tested that much, but it works as far as I know)

# How to use
First you'll need to configure the `DB` class
```
DB::confiigure([
    'host' => 'localhost',
    'port' => 3306,
    'user' => 'root',
    'pass' => 'pass',
    'dbname' => 'db'
]);
```

Then you can use the `query` method to execute raw queries or you can use the model functionality.
Example:
```
class User extends Model {};
$u = new User();
$u->name = 'Liam';
$u->save();
```