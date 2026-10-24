-- =====================================================================
-- Sample knowledge base - FICTIONAL DATA FOR DEMONSTRATION ONLY
--
-- These articles were written for this assignment. They are NOT official
-- Pine Labs documentation and do not describe any real product's menus,
-- response codes, SLAs or internal teams. Timelines and codes are
-- illustrative examples.
--
-- Safe to re-run: existing articles with the same article_code are
-- updated instead of duplicated.
--
-- To add an article: copy one INSERT row in the kb_seed block below,
-- give it a new article_code, and re-import this file (or insert it
-- directly into kb_articles via phpMyAdmin).
-- Multi-line fields use \n to separate items.
-- =====================================================================

USE troubleshoot_assistant;

INSERT INTO categories (slug, name, description) VALUES
  ('pos-hardware',     'POS Terminal & Hardware',      'Card readers, printers, batteries and other device hardware'),
  ('network',          'Network & Connectivity',       'SIM, Wi-Fi, host connectivity and network configuration'),
  ('card-payments',    'Card Payments',                'Card declines, refunds, voids and duplicate charges'),
  ('upi-qr',           'UPI & QR Payments',            'UPI collect, dynamic and static QR payment issues'),
  ('settlement',       'Settlement & Reconciliation',  'Payouts, settlement delays and report mismatches'),
  ('portal-access',    'Merchant Portal & Access',     'Login, password, OTP and user access issues'),
  ('gateway-api',      'Payment Gateway & API',        'Online payment API, webhook and integration issues'),
  ('software-updates', 'Software & Updates',           'Terminal application and firmware update problems')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

DROP TEMPORARY TABLE IF EXISTS kb_seed;
CREATE TEMPORARY TABLE kb_seed (
  article_code          VARCHAR(20),
  category_slug         VARCHAR(60),
  title                 VARCHAR(200),
  symptoms              TEXT,
  causes                TEXT,
  steps                 TEXT,
  escalation_conditions TEXT,
  escalate_to           VARCHAR(100),
  keywords              VARCHAR(500)
);

INSERT INTO kb_seed VALUES
(
  'KB-001', 'network',
  'POS terminal cannot connect to the network or payment host',
  'Terminal shows "No network", "Connection failed" or "Host not reachable"\nTransactions fail before the card is processed\nSIM or Wi-Fi signal icon is missing or shows no bars',
  'Weak mobile signal or inactive SIM data plan\nWi-Fi router is down or the Wi-Fi password was changed\nIncorrect APN or network settings after a configuration change\nMerchant firewall blocking outbound connections from the terminal',
  'Check the signal and Wi-Fi icons on the terminal status bar and note what is shown.\nRestart the terminal by holding the power button for about 10 seconds.\nFor Wi-Fi: confirm the router works using another device, then forget and re-enter the Wi-Fi network on the terminal.\nFor SIM: power off, reseat the SIM card, power on, and confirm with the merchant that the data plan is active.\nCompare the APN and network settings with the merchant provisioning sheet and correct any mismatch.\nRun the terminal connectivity test from the settings menu and record the result.\nTry a low-value test transaction once the connectivity test passes.',
  'Connectivity test fails on both SIM and Wi-Fi\nMore than one terminal at the same merchant location is affected\nIssue continues more than 30 minutes after completing all steps',
  'L2 Network Operations',
  'network, connection, connect, offline, no network, wifi, sim, signal, host, not reachable, unreachable, apn, internet, connectivity, firewall'
),
(
  'KB-002', 'card-payments',
  'Card transaction declined with a response code',
  'Terminal shows "Declined" with a response code on the screen or charge slip\nCustomer says the same card works at other merchants\nDeclines happen for one card or for many cards',
  'Issuer declined the transaction (for example insufficient funds or do not honour)\nCard expired or incorrect PIN entered\nCard not enabled for this type of transaction (international, contactless or online)\nTerminal date and time are incorrect',
  'Note the exact response code shown on the screen or charge slip.\nLook up the code in the fictional sample table: 05 = do not honour, 51 = insufficient funds, 54 = expired card, 55 = incorrect PIN, 91/96 = issuer or system unavailable.\nFor issuer-side codes (05, 51, 54, 55), ask the customer to use another card or contact their issuing bank.\nRetry once by inserting the chip instead of tapping or swiping.\nConfirm the terminal date and time are correct.\nIf several different cards decline with the same code in a short time, treat it as a terminal or host issue rather than a card issue.',
  'Several different cards declined within a short period\nResponse code indicates a system error (for example 91 or 96)\nResponse code is not in the reference table',
  'L2 Payments Operations',
  'declined, decline, card declined, response code, error code, rejected, do not honour, insufficient funds, expired card, incorrect pin, issuer, 05, 51, 54, 55, 91, 96'
),
(
  'KB-003', 'pos-hardware',
  'Card cannot be read (chip, swipe or contactless)',
  'Terminal shows "Card read error" or "Chip error, please swipe"\nContactless tap is not detected\nThe problem happens with many customer cards',
  'Dirty or damaged chip on the customer card\nDirty or faulty card reader in the terminal\nCard inserted the wrong way or not fully\nContactless reader disabled or antenna fault',
  'Ask the customer to insert the card chip-first, face up, fully into the slot and leave it until prompted.\nTry a second, known-good card to separate a card problem from a terminal problem.\nClean the chip reader slot with an approved cleaning card if one is available.\nIf contactless is supported, check that it is enabled in the terminal settings and try a tap.\nRestart the terminal.\nRun the card reader diagnostic from the maintenance menu and record the result.',
  'Known-good cards also fail to read\nVisible physical damage to the reader or terminal\nReader diagnostic reports a hardware fault',
  'L2 Field Service (Hardware)',
  'card read, read error, chip, chip error, reader, card reader, insert, swipe, magstripe, tap, contactless, nfc, emv, card not read'
),
(
  'KB-004', 'pos-hardware',
  'Receipt printer not printing, printing blank, or paper jam',
  'No charge slip printed after a transaction\nPrinted receipt is blank or very faint\nTerminal shows "Paper out" or "Printer error"\nPaper is jammed in the printer',
  'Paper roll is empty or loaded the wrong way (thermal side not facing the print head)\nPaper jam or printer cover not fully closed\nLow battery preventing printing\nWrong type of paper roll',
  'Open the printer cover and check that a paper roll is present.\nReload the roll so the thermal (shiny) side faces the print head, then close the cover firmly.\nRemove any torn or jammed paper.\nCharge the terminal so the battery is above 20 percent.\nPrint a test receipt from the terminal menu.\nIf a transaction completed but the slip did not print, use the reprint-last-receipt option. Check the transaction status first and do not run the payment again.',
  'Test print fails with correct paper and sufficient battery\nMechanical damage to the printer or cover',
  'L2 Field Service (Hardware)',
  'printer, print, printing, receipt, charge slip, slip, paper, paper roll, roll, paper jam, jam, blank, faint, thermal, paper out'
),
(
  'KB-005', 'upi-qr',
  'UPI or QR payment debited from customer but pending or failed on terminal',
  'Customer shows a debit message or successful payment in their UPI app\nTerminal or portal shows the transaction as pending or failed\nDynamic QR timed out while the customer was paying',
  'Delayed status confirmation from the payment network or bank\nTerminal lost network connection before the status updated\nQR code expired before the payment completed',
  'Do not ask the customer to pay again until the status of the first payment is confirmed.\nAsk the customer for the UTR or reference number from their UPI app.\nUse the check-status or verify-payment option on the terminal, or search the merchant portal by UTR.\nIf the status is still pending, wait for the status refresh window (15 minutes in this fictional sample process) and check again.\nIf the payment is confirmed successful, complete the sale and reprint the receipt.\nIf the payment is confirmed failed, inform the customer that the debit is normally reversed automatically by their bank and share the reference number.',
  'Status still pending after the refresh window\nUTR cannot be found in the portal\nCustomer reports the amount was debited more than once',
  'L2 Payments Operations',
  'upi, qr, qr code, dynamic qr, pending, debited, money debited, amount deducted, deducted, utr, rrn, not received, payment pending, vpa, scan'
),
(
  'KB-006', 'settlement',
  'Settlement not received or delayed',
  'Merchant reports that funds were not credited to the bank account\nSettlement amount is lower than expected\nTerminal batch shows as not settled',
  'Terminal batch was not settled or closed\nBank holiday or weekend affecting the payout cycle\nSettlement placed on hold for account or KYC verification\nRefunds, chargebacks or charges deducted from the payout\nIncorrect bank account details on file',
  'Confirm the transaction dates and the expected settlement cycle (T+1 working day in this fictional sample).\nCheck the terminal settlement report to confirm the batch was settled.\nCheck the settlement status and payout reference (UTR) in the merchant portal.\nReview deductions such as charges, refunds and chargebacks in the settlement report.\nCheck the bank holiday calendar for the settlement dates.\nCheck whether the merchant account has any active hold flag.',
  'Payout overdue beyond the settlement cycle with no hold or holiday reason\nMerchant account is on hold\nDifference cannot be explained by deductions',
  'L2 Settlements Team',
  'settlement, settled, not settled, payout, credited, not credited, funds, bank account, money not received, delayed, delay, batch, utr, hold'
),
(
  'KB-007', 'settlement',
  'Settlement report does not match transaction report',
  'Totals in the settlement report and the transaction report differ\nSome transactions appear missing or duplicated in one report',
  'Different date range or cut-off time used in the two reports\nVoids and refunds counted differently\nTransactions made after the batch cut-off moved to the next settlement\nTime zone differences in exported files',
  'Use the same date range and cut-off time for both reports.\nExport both reports for the same batch or settlement reference.\nMatch transactions using the RRN or transaction ID.\nIdentify voids, refunds and chargebacks separately.\nRecalculate the net amount as gross sales minus charges, refunds and chargebacks, and compare again.',
  'Unmatched transactions remain after aligning date range and cut-off\nSuspected duplicate or missing settlement',
  'L2 Settlements Team',
  'mismatch, reconciliation, reconcile, report, settlement report, difference, does not match, missing transaction, duplicate entry, totals, export'
),
(
  'KB-008', 'portal-access',
  'Merchant portal login failure, account locked or OTP not received',
  'Portal shows "Invalid credentials" or "Account locked"\nOTP for login or password reset is not received\nUser was able to log in earlier',
  'Incorrect password or user ID\nAccount locked after repeated failed attempts\nOTP sent to an old mobile number or email address\nBrowser cache or saved password issue\nUser account deactivated by the merchant admin',
  'Confirm the user is entering the correct user ID, which may differ from their email address.\nUse the "Forgot password" option to reset the password.\nIf the account is locked, wait for the lockout period (30 minutes in this fictional sample) or ask the merchant admin to unlock it.\nConfirm the registered mobile number and email address without reading them out in full.\nAsk the user to check spam folders and SMS blocking settings for the OTP.\nTry a private browser window or clear the browser cache.',
  'Registered mobile or email must be changed (identity verification required)\nAccount is deactivated\nAny sign of unauthorised access - escalate to security immediately',
  'L2 Access Management',
  'login, log in, sign in, password, forgot password, locked, account locked, otp, otp not received, portal, merchant portal, access, credentials, reset, invalid credentials'
),
(
  'KB-009', 'gateway-api',
  'Payment gateway API returns 401 or 403 authentication error',
  'API responds with 401 Unauthorized or 403 Forbidden\nError message mentions invalid signature, key or token\nIntegration works in sandbox but fails in production',
  'Sandbox keys used against the production endpoint (or the reverse)\nAPI key rotated, expired or disabled\nRequest signature calculated incorrectly\nServer clock out of sync\nCaller IP address not on the allowlist',
  'Confirm the base URL and the API key pair belong to the same environment (sandbox or production).\nCheck in the merchant dashboard that the key is active and not expired.\nRecalculate the request signature using the exact raw request body, as described in the integration guide.\nSync the merchant server time using NTP.\nCheck whether the calling server IP address is on the allowlist.\nRetry with a single test request and record the request ID and timestamp.',
  'Correct keys and signature still fail\nKeys may have been exposed - rotate them and inform the security team',
  'L2 Integration Support',
  'api, 401, 403, unauthorized, unauthorised, forbidden, authentication, auth, signature, invalid signature, hmac, api key, key, secret, token, sandbox, production, integration'
),
(
  'KB-010', 'gateway-api',
  'Payment gateway API timeouts or 5xx server errors',
  'Requests time out\nAPI responds with 500, 502, 503 or 504 errors\nFailures are intermittent',
  'Client timeout configured too short\nNetwork problem between merchant server and gateway\nGateway-side incident or maintenance\nRetries sent without an idempotency key',
  'Check the service status page or incident channel for any ongoing incident.\nConfirm the client timeout matches the integration guide (30 seconds in this fictional sample).\nBefore retrying a payment request, query the transaction status to avoid charging the customer twice.\nRetry with exponential backoff and an idempotency key.\nRecord the request ID, endpoint, timestamp and error code for each failure.',
  'Error rate above 5 percent for more than 10 minutes\nMultiple merchants reporting the same errors (possible incident)\nCustomers report duplicate charges',
  'L2 Integration Support / Incident Management',
  'timeout, timed out, time out, 500, 502, 503, 504, server error, 5xx, gateway, slow, latency, api, intermittent, bad gateway, service unavailable'
),
(
  'KB-011', 'gateway-api',
  'Webhook or payment callback not received',
  'Orders remain pending even though payment succeeded\nNo webhook notifications arrive at the merchant server',
  'Webhook URL misconfigured in the dashboard\nMerchant endpoint returns a non-2xx response or responds too slowly\nInvalid or expired SSL certificate on the merchant endpoint\nFirewall blocking incoming requests\nSignature verification failing on the merchant side',
  'Verify the webhook URL configured in the merchant dashboard.\nCheck the merchant server logs for incoming webhook requests and their response codes.\nMake sure the endpoint returns HTTP 200 quickly and processes the event afterwards.\nCheck that the SSL certificate on the endpoint is valid.\nCheck firewall rules for incoming requests.\nUse the transaction status API as a fallback to update pending orders.',
  'Webhooks still not received after the URL and endpoint are verified\nLarge number of orders stuck in pending status',
  'L2 Integration Support',
  'webhook, callback, notification, not received, pending order, order pending, endpoint, ssl, certificate, url, event'
),
(
  'KB-012', 'software-updates',
  'Terminal software update failed or terminal stuck on startup',
  'Terminal shows "Update failed"\nTerminal is stuck on the startup logo or keeps restarting\nPayment app crashes after an update',
  'Download interrupted by network loss\nLow battery during the update\nInsufficient storage on the device\nCorrupted update package',
  'Connect the charger and make sure the battery is above 50 percent.\nMake sure the terminal has a stable network connection.\nRestart the terminal and retry the update from the settings menu.\nIf the terminal is stuck on the logo, hold the power button for about 15 seconds to force a restart.\nIf possible, settle the current batch before any further action.\nDo not factory reset the terminal at L1; unsettled transactions may be lost.',
  'Terminal remains stuck in a restart loop\nThere is an unsettled batch on a terminal that cannot start\nA factory reset is being considered',
  'L2 Terminal Management',
  'update, update failed, upgrade, firmware, software, stuck, boot, startup, logo, restart loop, restarting, crash, app crash, version'
),
(
  'KB-013', 'pos-hardware',
  'Terminal battery not charging or terminal switching off',
  'Terminal does not charge when connected\nTerminal switches off during use\nBattery drains very quickly',
  'Faulty charger or cable\nDirty or damaged charging port\nBattery worn out\nNon-original adapter in use',
  'Use the original charger and cable.\nTry a different power socket.\nInspect the charging port for dust or damage.\nSwitch the terminal off and charge it for 30 minutes.\nCheck battery health in the terminal settings if available.',
  'Battery appears swollen - stop using the terminal immediately (safety risk)\nTerminal does not charge with a known-good charger',
  'L2 Field Service (Hardware)',
  'battery, charging, not charging, charge, charger, power, switches off, switching off, shuts down, turns off, dead, drain, drains'
),
(
  'KB-014', 'card-payments',
  'Refund or void failed or not reflecting for the customer',
  'Refund shows as failed in the terminal or portal\nCustomer has not received a refund\nVoid option is not available for a transaction',
  'Void is only possible before the batch is settled; after settlement a refund is needed\nRefund amount exceeds the original transaction amount\nRefund processed but the customer bank has not yet credited it\nInsufficient balance for refunds on the merchant account',
  'Find the original transaction using the RRN or transaction ID.\nCheck whether the batch is settled: use void before settlement and refund after settlement.\nConfirm the refund amount does not exceed the original amount.\nCheck the refund status in the merchant portal.\nShare the refund reference number with the customer and explain that banks can take several working days to credit it (5 to 7 in this fictional sample).',
  'Refund failed with a system error\nRefund not credited after the expected timeline\nRefund amount does not match the request',
  'L2 Payments Operations',
  'refund, refund failed, void, reversal, cancel transaction, cancel, return, money back, not reflecting, refund status, arn'
),
(
  'KB-015', 'card-payments',
  'Customer charged twice for one purchase (duplicate charge)',
  'Customer reports two debits for one purchase\nOne attempt appeared failed and was retried\nTwo identical transactions appear in the history',
  'Payment retried after a timeout without checking the first attempt\nGenuine duplicate transaction\nTemporary authorisation hold that will be reversed automatically',
  'Collect both reference numbers (RRN or UTR), amounts and times from the customer or charge slips.\nCheck the terminal transaction history or merchant portal to see whether both transactions were successful.\nIf one transaction failed or is only a temporary hold, explain that it is normally reversed automatically and share the reference.\nIf both are successful, void or refund one of them by following KB-014.\nNever retry a payment before the status of the previous attempt is confirmed.',
  'Both transactions settled and the refund cannot be processed at L1\nCustomer has raised a dispute or chargeback\nSimilar duplicate charges reported by several customers',
  'L2 Payments Operations',
  'duplicate, duplicate charge, charged twice, double charge, double, twice, two times, debited twice, two debits, multiple debit, charged two times'
);

INSERT INTO kb_articles
  (article_code, title, category_id, symptoms, causes, steps,
   escalation_conditions, escalate_to, keywords, is_fictional, is_active)
SELECT s.article_code, s.title, c.id, s.symptoms, s.causes, s.steps,
       s.escalation_conditions, s.escalate_to, s.keywords, 1, 1
FROM kb_seed s
JOIN categories c ON c.slug = s.category_slug
ON DUPLICATE KEY UPDATE
  title                 = VALUES(title),
  category_id           = VALUES(category_id),
  symptoms              = VALUES(symptoms),
  causes                = VALUES(causes),
  steps                 = VALUES(steps),
  escalation_conditions = VALUES(escalation_conditions),
  escalate_to           = VALUES(escalate_to),
  keywords              = VALUES(keywords),
  is_fictional          = VALUES(is_fictional);

DROP TEMPORARY TABLE IF EXISTS kb_seed;
