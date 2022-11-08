# Waze for Cities Alert Notifier

This is a small PHP application I've written for a Waze for Cities partner to transform the Waze for Cities alerts feed into e-mail alerts. The code was somewhat written with re-use in mind, but it's certainly "rough around the edges", so to say ;)

## Required PHP modules

* pdo
* the pdo module specifically for your database
* gd
* imap
* json

## Configuration

Make sure to adjust config.php to add the database connection information, the address to use as the sender address for notification mails and the token to access the Waze for Cities feed

Import install/create-database.sql in your database.
Feel free to remove the install directory afterwards.
Adjust the partner data to your wishes. Especially change the access tokens to two random strings.

The current implementation relies on the presence of a Waze livemap image to add an image with a pin within the e-mails.
This could potentially be generated by retrieving the necessary map tiles from Waze instead, but that is not implemented.
You'll need to create this image yourself in a GIS tool like QGis by adding an XYZ tiles layer for the URL `https://worldtiles1.waze.com/tiles/{z}/{x}/{y}.png`, exporting the image and then setting the correct coordinates in the `alert-notifier_partners`. No, this is not easy :(
See images/map_1.png and the create-database.sql script as an example.

Configure a cron job to automatically call the update every two minutes, or more frequently if Waze's data gets updated more frequently. Example:
`*/2 * * * * wget -q -O /dev/null <your site>/alert-notifier/update?access_token=<token of update service account here> >/dev/null 2>&1`

Go to `/alert-notifier/?access_token=<token of partner account here>` and add one or more rules to retrieve alerts for.

Please note that is application may create quite a few log files. There seems to be some bug in the deletion process that I haven't solved yet. I also in no way stress-tested this application.
