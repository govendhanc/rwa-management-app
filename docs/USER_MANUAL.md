# User Manual

## Login

Open the web application and sign in with the user account created by the Admin.

Roles:

- Admin: full access
- Treasurer: collections, expenses, income, and reports
- Read Only: dashboard and reports

## Dashboard

Use the dashboard to view collection status, outstanding amounts, expenses, available balance, and finance trends. The charts help the committee compare monthly collection, category-wise expenses, paid vs pending count, and income vs expense.

## Plot Owners

Use Plot Owners to search by plot number, owner name, or mobile number. Owner records include tenant details, occupancy status, street, block, plot size, water connection, EB connection, and remarks.

## Monthly Maintenance

1. Choose month and year.
2. Select Generate Month to create maintenance rows for all plots.
3. Enter or update paid amount through the API or future form expansion.
4. Status becomes Paid, Partially Paid, or Unpaid based on balance.
5. Use receipt actions for print, WhatsApp, and email workflows.

## Expenses

Add each expense with date, category, vendor, amount, payment mode, invoice number, and description. Upload storage can be connected to local disk or object storage.

## Income

Record corpus fund, donation, interest, penalty, membership fee, and other income.

## Reports

Choose a report and export it to Excel or PDF:

- Pending List
- Paid List
- Expense Report
- Income Report
- Cash Book
- Outstanding Aging

## Receipts

Receipts include association name, receipt number, date, plot number, owner name, payment details, amount, payment mode, treasurer signature, and QR payload. A4 and thermal layouts can be implemented as separate print templates.

## Notifications

The backend is prepared for WhatsApp, SMS, and email integration. Configure provider credentials and schedule reminders for unpaid rows.

Reminder template:

```text
Dear Owner,

Your maintenance fee for June 2026 is pending.

Amount: ₹500

Please pay at the earliest.

Regards,
Association Treasurer
```

## Settings

Admin can manage association details, maintenance amount, late fee, GST, financial year, receipt prefix, backup schedule, logo, UPI ID, and penalty configuration.

## Backups

For local hosting, back up MySQL with `mysqldump`.

```bash
mysqldump -u rwa_user -p rwa_db > rwa_backup.sql
```

Restore by loading the SQL dump into the local MySQL database.

```bash
mysql -u rwa_user -p rwa_db < rwa_backup.sql
```
