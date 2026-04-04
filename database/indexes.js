// Run using mongosh: load('database/indexes.js')

const dbName = process.env.MONGO_DB || 'bbn_ms';
const database = db.getSiblingDB(dbName);

// Customers
database.customers.createIndex({ customer_id: 1 }, { unique: true, name: 'uq_customer_id' });
database.customers.createIndex(
	{ phone: 1 },
	{
		unique: true,
		name: 'uq_customer_phone',
		partialFilterExpression: {
			phone: { $type: 'string', $ne: '' },
		},
	}
);
database.customers.createIndex({ zone_id: 1, area_id: 1 }, { name: 'idx_customer_zone_area' });
database.customers.createIndex({ status: 1 }, { name: 'idx_customer_status' });

// Products
database.products.createIndex({ sku: 1 }, { unique: true, sparse: true, name: 'uq_product_sku' });
database.products.createIndex({ category: 1 }, { name: 'idx_product_category' });
database.products.createIndex({ stock: 1, reorder_level: 1 }, { name: 'idx_product_stock_reorder' });

// Topology
database.zones.createIndex({ name: 1 }, { unique: true, name: 'uq_zone_name' });
database.areas.createIndex({ zone_id: 1, name: 1 }, { unique: true, name: 'uq_area_zone_name' });
database.line_sources.createIndex({ name: 1 }, { unique: true, sparse: true, name: 'uq_line_source_name' });
database.distribution_boxes.createIndex({ zone_id: 1, area_id: 1 }, { name: 'idx_box_zone_area' });
database.distribution_boxes.createIndex({ line_source_id: 1 }, { name: 'idx_box_line_source' });

// Packages
database.packages.createIndex({ name: 1 }, { unique: true, name: 'uq_package_name' });
database.packages.createIndex({ status: 1 }, { name: 'idx_package_status' });

// Connection orders
database.connection_orders.createIndex({ customer_id: 1, connected_on: -1 }, { name: 'idx_connection_customer_date' });
database.connection_orders.createIndex({ status: 1 }, { name: 'idx_connection_status' });

// Bills & Payments
database.bills.createIndex({ customer_id: 1, billing_month: 1 }, { unique: true, name: 'uq_bill_customer_month' });
database.bills.createIndex({ status: 1, due_amount: 1 }, { name: 'idx_bill_status_due' });
database.payments.createIndex({ customer_id: 1, created_at: -1 }, { name: 'idx_payment_customer_created' });
database.payments.createIndex({ bill_month: 1 }, { name: 'idx_payment_bill_month' });

// Finance
database.income_entries.createIndex({ date: -1, category: 1 }, { name: 'idx_income_date_category' });
database.expense_entries.createIndex({ date: -1, category: 1 }, { name: 'idx_expense_date_category' });

print('Indexes created for bbn_ms project');
