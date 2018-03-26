# Product load instructions

* copy the product images and product csv file into
`/Users/jake/Code/ewhale/MENA/Bundle/MENALoadDataBundle/Migrations/Data/ORM/data`

## Image file processing 

* rename the image folder to `images`
* run `sh convertimages.sh images`. This script will convert all images to jpg, reduce image sizes and remove `-` 
and spaces in files names and folder names.
* delete the old images
 `rm -rf ../images/products`
* copy the new product images to the load folder `cp -rf images ../images/products`

## Check product csv file

* rename the product csv file to `products.csv`
* check duplicate sku codes
* price list must value
* check column title

## Run a load in a test environment 

