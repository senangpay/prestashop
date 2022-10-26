# senangPay for PrestaShop 1.7
Accept payment using senangPay for PrestaShop 1.7

## Installation
1. Download Module: Link to your download URL.
2. **Open** the archive and **rename** the folder inside from **something** to **senangpay**.
3. Upload and **Install to Prestashop**.
4. Configure your **Environment, Merchant ID & Secret Key**.
5. Go to Payment > Preferences, & Set senangPay Currency restrictions to Malaysian Ringgit (MYR).
6. Done.

## senangPay Dashboard Shopping Cart Integration Configuration
1. Log into your senangPay dashboard.
2. Go to Settings > Profile, scroll down to Shopping Cart Integration Link section.
3. Fill up Return URL with [your shop URL]/module/senangpay/return (ex: https://www.myprestashop/module/senangpay/return).
4. Fill up Callback URL with [your shop URL]/module/senangpay/return (ex: https://www.myprestashop/module/senangpay/return).
5. Make sure you choose SHA256 for the Hash Type Preference
6. Save.
7. Done.

## System Requirements
1. PHP 7.0 or later.
2. Prestashop **1.7.x**.
3. **Not compatible with Prestashop 1.6** and below.
