#!/bin/bash

# remove all spaces folder names and file names
find $1 -type d  -exec  rename 's/ //g' {}  +
find $1 -type f  -exec  rename 's/ //g' {}  +

find $1 -type f -not -name "*.jpg" -exec ls {} +
#convert all non jpg images to jpg
find $1 -type f -not -name "*.jpg" -exec mogrify -format jpg {} +
#remove all non jpg images
find $1 -type f -not -name "*.jpg" -exec rm -rf {} +
#reduce jpg image quality by 60%
find $1 -type f -name "*.jpg" -exec convert {} -sampling-factor 4:2:0 -strip -quality 60 -interlace JPEG -colorspace sRGB {} \;