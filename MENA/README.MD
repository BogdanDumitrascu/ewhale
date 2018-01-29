

##Change banner images

image paths
`MENA/Bundle/MENAThemeBundle/Resources/public/catalogbundle/images/promo-slider/`

image names
* promo-slider-1.jpg

## convert to the correct format and size

`convert banner.png promo-slider-1.jpg`

`file promo-slider-1.jpg` get the image size

`convert promo-slider-1.jpg -resize 1260 promo-slider-1.jpg`

`git comment`

`git push`

login into the server

`git pull`

`sh rebuild.sh`

##Change logo 