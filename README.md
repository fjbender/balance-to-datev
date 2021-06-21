# Convert Mollie Balance Report to DATEV

## General
This is a proof-of-concept type of solution to convert a Mollie balance report into the DATEV CSV ASCII format.

Please keep in mind that this is only a prototype and might not be ready for production use!

## Installation and usage

1. Clone the repo
1. `cd balance-to-datev`
1. `composer install`

Then you can copy `config.neon.dist` to `config.neon` and adjust the ledger numbers to your need. If you are using SKR04 for your bookkeeping, you should be fine with the predefined ledger numbers.

The converter expects a Mollie Balance report for a whole month in its directory with the name "mollie-balance.csv". It will output a DATEV compatible report as "mollie-datev.csv".

Once you provided a Mollie Balance report in the directory, simply run `php converter.php`. You'll see something like:

```
Read mollie-balance.csv, 698 lines processed.
DATEV report written to mollie-datev.csv with 698 lines.
```
## License

[BSD (Berkeley Software Distribution) License](https://opensource.org/licenses/bsd-license.php).

Copyright (c) 2021, Florian Bender