#!/bin/bash
#
# Smarty templates library quick installation (with sources download)
#

SMARTYVER="3.1.6"

cd ../lib
# download
echo -n "Downloading Smarty sources... "
wget http://www.smarty.net/files/Smarty-$SMARTYVER.tar.gz
echo "done."

# extracting package
echo -n "Extracting... "
tar -xzf Smarty-$SMARTYVER.tar.gz
echo "done."
exit
# merging
echo -n "Merging... "
mkdir Smarty > /dev/null
mv Smarty-$SMARTYVER/libs/* Smarty/
mv Smarty-$SMARTYVER/libs/plugins/* Smarty/plugins/
echo "done."

# cleanup
echo -n "Cleaning up... " 
rm -Rf Smarty-$SMARTYVER Smarty-$SMARTYVER.tar.gz
echo "done."
