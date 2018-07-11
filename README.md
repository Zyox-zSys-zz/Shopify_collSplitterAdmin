# Shopify Collection Splitter
### Split Shopify Product Collections into mini-collections of up to a selectable amount of items.
Uses the Promise API to prevent unresponsiveness even with massive lists of collections.
###### Requires: ES8+ (latest Javascript; Firefox and Chrome are up-to-date), and PHP6+ (if self-hosted) and SSL (required by Shopify)

#### Setup:

##### As app:
https://zyox.ihostfull.com/shopify/collSplitterAdmin.php

##### As private app (replacing brackets):
https://[APIkey]:[APIpw]@zyox.ihostfull.com/shopify/collSplitterAdmin.php

##### As self-hosted private app (replacing brackets):
https://[APIkey]:[APIpw]@[hostAndPath]/collSplitterAdmin.php

##### As self-hosted app:
- Create a Shopify Partner account, then go to Apps > create new
- Enter any name; the URL is the address to where you hosted the collSplitterAdmin.php file
- Once saved, copy the provided API key and shared secret into the ShopifyAPI.ini file
- Then visit that same URL to collSplitterAdmin.php

###### To log out from private mode and install instead, cancel all pop-ups when visiting:
https://zyox.ihostfull.com/shopify/collSplitterAdmin.php?logout
