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

One owner can be linked to multiple plots. While adding an owner, enter multiple plot numbers separated by commas, such as `A-001, A-003`. The system validates that plot numbers are unique and blocks duplicate plot numbers.

Use Edit to update owner and plot details. If an owner has multiple plots, owner details such as name, mobile, email, address, occupancy, tenant, and remarks are shared across those plots. Use Delete on a plot row to remove only that plot.

## Monthly Maintenance

1. Choose month and year.
2. Select Generate Month to create maintenance rows for all plots.
3. Use Add Payment to enter a new collection.
4. Use Edit to update payment amount, date, mode, receipt number, and remarks.
5. Use Delete to remove an incorrect entry. Deleted entries are kept in audit history.
6. Use Print to open the printable PDF receipt.
7. Status becomes Paid, Partially Paid, or Unpaid based on balance.

## Expenses

Add each expense with date, category, vendor, amount, payment mode, invoice number, and description. Use Edit to correct an expense and Delete to remove an incorrect entry. Deleted entries are kept in audit history. Upload storage can be connected to local disk or object storage.

## Income

Record corpus fund, donation, interest, penalty, membership fee, and other income. Use Edit to correct an income entry and Delete to remove an incorrect entry. Deleted entries are kept in audit history.

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

The receipt header uses the association logo, registration number, address, mobile number, and email configured in Association Settings.

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
