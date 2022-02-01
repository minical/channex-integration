<p>
	<h1 align="center">
	Channex Integration</h1>
</p>

## Table of Contents
* [Introduction](#introduction)
* [Installation](#installation)
* [Features](#features)
* [Dependencies](#dependencies)
* [Versioning](#versioning)
* [License](#license)

### Introduction
This extension helps integrate miniCal with Channex to connect to OTA's like Booking, Airbnb, and Expedia.
 

### Installation
* Fork the repository https://github.com/minical/channex-integration or clone it locally, or you can get this extension from [miniCal MarketPlace](https://marketplace.minical.io/product/channex-integration). 
* Upload the extension folder into the /public/application/extensions directory.
* Activate the extension through the "Extensions" screen in miniCal.
* Click on the setting icon. It will redirect you to the Channex setting page. Fill in all the details, and map rooms with rate-plan then you are good to go.
* Retrieve Bookings from Channex Automatically: To automatically fetch booking from Channex, you need to set a cron job on your server. Hit following URL every minute or every 30 seconds (Whatever you prefer) using cron to retrieve bookings automatically: https://app.minical.io/cron/ota_booking_retrieval

<img src="https://snipboard.io/NkMWRi.jpg" alt=""> 
<img src="https://snipboard.io/mQay3S.jpg" alt=""> 

### Features
* Easily connect to miniCal.
* Credit Card Tokenisation.
* Connect to all major OTA channels such as Airbnb, Booking & Expedia
* instantly sync all connected channels in real-time.

### Dependencies
A Channex account. 

### Versioning

The version is broken down into 4 points e.g 1.2.3.4 We use MAJOR.MINOR.FEATURE.PATCH to describe the version numbers.

A MAJOR is very rare, it would only be considered if the source was effectively re-written or a clean break was desired for other reasons. This increment would likely break most 3rd party modules.

A MINOR is when there are significant changes that affect core structures. This increment would likely break some 3rd party modules.

A FEATURE version is when new extensions or features are added (such as a payment gateway, shipping module etc). Updating a feature version is at a low risk of breaking 3rd party modules.

A PATCH version is when a fix is added, it should be considered safe to update patch versions e.g 1.2.3.4 to 1.2.3.5

### License

[The Open Software License 3.0 (OSL-3.0)](https://github.com/minical/channex-integration/blob/main/LICENSE.md)
