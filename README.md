## Goals

* Register and login system, so they can resume
* Multiple pages
* Qualifications

## Implementation Notes

We're using a router script so there's a single entry point, to reduce boilerplate. We're using surrogate keys in the database to support renaming.

## Local Testing

Make sure you have database access from your home IP. Create an environment.conf file with the `DB*` constants. Run with `php -S localhost:8000 -c php.ini`.

To import, run something like `tail -n +2 LTUE\ 2020\ Call.csv | php -c php.ini import.php`.


## Production Use

To export, run something like `mysqldump --defaults-file=db.cnf ucevent1_LTUE > db-2019-09-03.sql`.

`DELETE FROM panels;` - should remove all related table entries via foreign key cascading.
