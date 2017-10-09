# Drop recaptcha related settings from database, we will use .env from now on

DELETE FROM settings WHERE setting = 'registerrecaptcha';
DELETE FROM settings WHERE setting = 'recaptchasitekey';
DELETE FROM settings WHERE setting = 'recaptchasecretkey';
DELETE FROM settings WHERE setting = 'recaptchenabled';

