# Laravel sample

## Assign codes to products

This PHP class is responsible for assigning codes to products. It is a background operation. For each product in a batch there is a set number of codes, which are saved when their quantity reaches every 100. If the amount of codes is insufficient, there is a 30 second delay. Otherwise, they are exported into the archive.