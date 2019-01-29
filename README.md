# limesharp_stockimport
Stock Import Module For Magento 2

This module was developed and tested on Magento 2.2.5 as Technical Test.

The brief for the test is create a Magento 2 extension that imports a csv stock file every 10 minutes.
The stock file will be placed into the var/import folder every 10 minutes, the extension should run on the scheduler and do the following:

1) Check the file exists in the import folder
2) If there is a file already been processed wait until this is finished & successful
3) Prevent empty files being processed
4) Process file and update new stock values
5) Ensure In Stock & Out Of Stock settings are triggered 
6) Manage indexes & cache
7) Detailed logging at each failure point

The file will be in the following pipe delimited CSV format:
SKU | 3

Example of var/import/stock.csv:
L32C0209101.00U|10
L31A2402100.00U|14
L00A2302304.00U|0
L34A2104706.00U|1
L34A2104403.00U|0
