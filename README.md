Paymentsense Module for Magento 1.9 CE
====================================

Payment module for Magento 1.9 Community Edition, allowing you to take payments via Paymentsense.


Requirements
------------

* Magento Community Edition 1.9 (tested up to 1.9.4.0)
* PCI-certified server using SSL/TLS (required for Direct and MOTO payment methods)


Installation (via Magento Connect)
-------------------

1. Login to the Magento Admin Panel
2. Go to ```System``` -> ```Magento Connect``` -> ```Magento Connect Manager```
3. Search for the Paymentsense Module for Magento 1.9 CE at Magento Connect via Magento Connect and get the Extension Key
4. Paste the extension key in the respective field in the Magento Connect Manager and click the **Install** button


Installation (via Direct package file upload)
-------------------

1. Download the Paymentsense Module for Magento 1.9 CE package file from the Paymentsense Developer Zone at http://developers.paymentsense.co.uk
2. Login to the Magento Admin Panel
3. Go to ```System``` -> ```Magento Connect``` -> ```Magento Connect Manager```
4. Click the **Browse** button and select the package file downloaded in step 1
5. Click the **Upload** button


Configuration
-------------

1. Login to the Magento Admin Panel
2. Go to **System** -> **Configuration** -> **Sales** -> **Payment Methods**
3. Click the **Expand** button next to the payment methods **Paymentsense Hosted**, 
  **Paymentsense Direct** or/and **Paymentsense MOTO** to expand the configuration settings
4. Set **Enabled** to **Yes**
5. Set the gateway credentials and pre-shared key where applicable
6. Optionally, set the rest of the settings as per your needs
7. Click the **Save Config** button


Secure Checkout
---------------

The usage of the **Paymentsense Direct** and **Paymentsense MOTO** involves the following additional steps:

1. Make sure SSL/TLS is configured on your PCI-DSS certified server
2. Login to the Magento Admin Panel
3. Go to **System** -> **Configuration** -> **General** -> **Web** 
4. Expand the **Secure** section 
5. Set **Use Secure URLs in Frontend** and **Use Secure URLs in Admin** to **Yes**
6. Set your **Base URL** 
7. Click the **Save Config** button


Changelog
---------

### 1.9.3 (1.9.3001)
##### Added
- SERVER result delivery method (Paymentsense Hosted)
- Payment method status and gateway connection status on the payment methods settings pages
- Gateway connection status on the module information report
- Ability to disable the communication on port 4430 (Paymentsense Hosted)

##### Fixed
- Switching to the next gateway entry point when an unexpected response from the gateway is received


### 1.9.2
##### Added
- Module information reporting feature

##### Removed
- gw3 gateway entry point


### 1.9.1
##### Changed
- Order email queued only after successful payment. Emails for failed payments are no longer sent to the customer.


### 1.9.0
Initial Release


Support
-------

[devsupport@paymentsense.com](mailto:devsupport@paymentsense.com)
