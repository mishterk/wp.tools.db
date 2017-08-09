# WP DB Tools

Some tools for working with the DB in WordPress.

## DB\Model()

Simple base model for modeling custom DB tables in WordPress. Using this class will allow you to; 

1. setup the table schema
2. define primary key (supports composites)
3. create/update the database using the `create_table()` method (via WP's built-in `dbDelta()` function)
4. drop the table
5. run various queries on your table such as;
    1. Get a single row by ID (composite supported)
    1. Get rows where xxx
    2. Insert new rows
    1. Insert or update a row, if it already exists
    3. Delete a row

To use, extend `\Mishterk\WP\Tools\DB\Model` with your own model and implement required methods. You can then call the 
`$model->create_table()` method on your instance to create/update the database with your new table.

See the following test classes for a look at how your custom class will look;

`tests/resources/class-test-model.php`
`tests/resources/class-test-model-composite-key.php`

Currently, the class will set itself up with the necessary dependencies (`$wpdb` and a built in adaptor), but these can
be overridden using a constructor arg array. 