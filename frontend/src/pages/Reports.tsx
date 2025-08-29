import React, { useState, useEffect } from 'react';
import api from '../services/api';
import { StatsCardSkeleton, TableRowSkeleton } from '../components/Skeleton';

interface SalesStats {
  total_sales: number;
  total_amount: number;
  total_items: number;
  avg_sale: number;
}

interface Sale {
  id: number;
  date: string;
  cashier_name: string;
  items_count: number;
  total_amount: number;
  discount_amount: number;
  final_amount: number;
  payment_method: string;
}

interface SaleDetails {
  id: number;
  date: string;
  total_amount: number;
  discount_amount: number;
  tax_amount: number;
  subtotal_amount: number;
  cash_amount: number;
  card_amount: number;
  payment_method: string;
  items: SaleItem[];
}

interface SaleItem {
  id: number;
  // Some endpoints return `product_name` instead of `name`.
  // Keep both for compatibility with existing data.
  name?: string;
  product_name?: string;
  quantity: number;
  price: number;
  tax_rate: number;
}

const Reports: React.FC = () => {
  const [stats, setStats] = useState<SalesStats>({
    total_sales: 0,
    total_amount: 0,
    total_items: 0,
    avg_sale: 0
  });
  const [sales, setSales] = useState<Sale[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('today');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [showInvoiceModal, setShowInvoiceModal] = useState(false);
  const [selectedSale, setSelectedSale] = useState<SaleDetails | null>(null);

  useEffect(() => {
    fetchReports();
  }, [filter, startDate, endDate]);

  const fetchReports = async () => {
    try {
      setLoading(true);
      
      const params = new URLSearchParams();
      params.append('filter', filter);
      if (filter === 'custom' && startDate && endDate) {
        params.append('start_date', startDate);
        params.append('end_date', endDate);
      }

      const response = await api.get(`reports.php?${params.toString()}`);
      if (response.data.success) {
        setStats(response.data.stats);
        setSales(response.data.sales);
      }
    } catch (error) {
      console.error('Error fetching reports:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (newFilter: string) => {
    setFilter(newFilter);
    if (newFilter !== 'custom') {
      setStartDate('');
      setEndDate('');
    }
  };

  const viewInvoice = async (saleId: number) => {
    try {
      const response = await api.get(`sale_details.php?sale_id=${saleId}`);
      if (response.data.success) {
        setSelectedSale(response.data.sale);
        setShowInvoiceModal(true);
      } else {
        alert('Error loading invoice: ' + response.data.message);
      }
    } catch (error) {
      console.error('Error loading invoice:', error);
      alert('Error loading invoice');
    }
  };

  const printInvoice = () => {
    if (!selectedSale) return;

    const printWindow = window.open('', '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes');
    
    if (!printWindow) {
      alert('Popup blocked! Please allow popups for this site and try again.');
      return;
    }

    let itemsHTML = '';
    selectedSale.items.forEach(item => {
      const price = parseFloat(item.price.toString());
      const quantity = parseInt(item.quantity.toString());
      const taxRate = parseFloat(item.tax_rate.toString()) || 0;
      const itemTotal = price * quantity;
      const itemSubtotal = taxRate > 0 ? (itemTotal / (1 + taxRate / 100)) : itemTotal;
      const itemTax = itemTotal - itemSubtotal;
      const displayName = (item as { product_name?: string; name?: string }).product_name ?? (item as { product_name?: string; name?: string }).name ?? '';
      
      itemsHTML += `
        <tr>
          <td style="padding: 2px; font-size: 12px;">${displayName}</td>
          <td style="padding: 2px; font-size: 12px; text-align: center;">${item.quantity}</td>
          <td style="padding: 2px; font-size: 12px; text-align: right;">${price.toFixed(2)}</td>
          <td style="padding: 2px; font-size: 12px; text-align: right;">${itemTotal.toFixed(2)}</td>
        </tr>
        <tr><td colspan="4" style="padding: 1px 2px; font-size: 10px; color: #666; text-align: right;">Tax: Rs. ${itemTax.toFixed(2)} (${taxRate}%)</td></tr>
        <tr style="height: 2px;"><td colspan="4" style="border-bottom: 1px solid #ddd; padding: 0;"></td></tr>
      `;
    });

    const invoiceHTML = `
      <!DOCTYPE html>
      <html>
      <head>
        <title>Invoice</title>
        <style>
          body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 2mm; 
            font-size: 11px; 
            line-height: 1.2; 
            width: 76mm; 
            overflow-x: hidden; 
          }
          table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 10px; 
            table-layout: fixed; 
          }
          th, td { 
            padding: 1px; 
            text-align: left; 
            word-wrap: break-word; 
          }
          th:nth-child(1), td:nth-child(1) { width: 35%; }
          th:nth-child(2), td:nth-child(2) { width: 15%; text-align: center; }
          th:nth-child(3), td:nth-child(3) { width: 25%; text-align: right; }
          th:nth-child(4), td:nth-child(4) { width: 25%; text-align: right; }
          .text-center { text-align: center; }
          .text-right { text-align: right; }
          .border-t { border-top: 1px solid #ccc; }
          .font-bold { font-weight: bold; }
          .duplicate-watermark { 
            position: absolute; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%) rotate(-30deg); 
            font-size: 30px; 
            color: rgba(0,0,0,0.08); 
            letter-spacing: 2px; 
            z-index: 0; 
            pointer-events: none; 
            user-select: none; 
            text-transform: uppercase; 
            font-weight: 900; 
          }
          @media print { 
            body { margin: 0; padding: 2mm; width: 76mm; } 
            @page { margin: 0; size: 80mm auto; } 
          }
        </style>
      </head>
      <body>
        <div class="print-wrapper" style="position: relative; width: 100%;">
          <div class="duplicate-watermark">DUPLICATE</div>
          <div style="font-size: 14px; line-height: 1.3; width: 100%; margin: 0; padding: 0 3mm;">
            <div style="margin-bottom: 3px; text-align: center;">
              <h2 style="font-size: 18px; margin: 0;">INVOICE</h2>
              <p style="font-size: 12px; margin: 2px 0;">Sale #${selectedSale.id}</p>
              <p style="font-size: 12px; margin: 2px 0;">${new Date(selectedSale.date).toLocaleDateString()} ${new Date(selectedSale.date).toLocaleTimeString()}</p>
            </div>
            
            <div style="margin-bottom: 3px;">
              <table>
                <thead>
                  <tr style="border-bottom: 1px solid #ccc;">
                    <th style="padding: 2px; font-size: 12px; width: 45%;">Item</th>
                    <th style="padding: 2px; font-size: 12px; width: 15%;">Qty</th>
                    <th style="padding: 2px; font-size: 12px; width: 20%;">Price</th>
                    <th style="padding: 2px; font-size: 12px; width: 20%;">Total</th>
                  </tr>
                </thead>
                <tbody>
                  ${itemsHTML}
                </tbody>
              </table>
            </div>
            
            <div style="margin-top: 3px; border-top: 1px solid #ccc;">
              <table style="width: 100%; border-collapse: collapse;">
                <tr>
                  <td style="text-align: left; padding: 1px 2px; font-size: 12px;">Subtotal:</td>
                  <td style="text-align: right; padding: 1px 2px; font-size: 12px;">Rs. ${parseFloat(selectedSale.subtotal_amount?.toString() || selectedSale.total_amount.toString()).toFixed(2)}</td>
                </tr>
                <tr>
                  <td style="text-align: left; padding: 1px 2px; font-size: 12px;">Tax:</td>
                  <td style="text-align: right; padding: 1px 2px; font-size: 12px;">Rs. ${parseFloat(selectedSale.tax_amount?.toString() || '0').toFixed(2)}</td>
                </tr>
                <tr>
                  <td style="text-align: left; padding: 1px 2px; font-size: 12px;">Discount:</td>
                  <td style="text-align: right; padding: 1px 2px; font-size: 12px;">-Rs. ${parseFloat(selectedSale.discount_amount.toString()).toFixed(2)}</td>
                </tr>
                <tr>
                  <td style="text-align: left; padding: 1px 2px; font-size: 14px; font-weight: bold;">Total:</td>
                  <td style="text-align: right; padding: 1px 2px; font-size: 14px; font-weight: bold;">Rs. ${(parseFloat(selectedSale.total_amount.toString()) - parseFloat(selectedSale.discount_amount.toString())).toFixed(2)}</td>
                </tr>
              </table>
            </div>
            
            <div style="margin-top: 3px; border-top: 1px solid #ccc;">
              <table style="width: 100%; border-collapse: collapse;">
                <tr>
                  <td style="text-align: left; padding: 1px 2px; font-size: 12px;">Payment Method:</td>
                  <td style="text-align: right; padding: 1px 2px; font-size: 12px;">${selectedSale.payment_method.toUpperCase()}</td>
                </tr>
                ${parseFloat(selectedSale.cash_amount?.toString() || '0') > 0 ? `<tr><td style="text-align: left; padding: 1px 2px; font-size: 12px;">Cash:</td><td style="text-align: right; padding: 1px 2px; font-size: 12px;">Rs. ${parseFloat(selectedSale.cash_amount?.toString() || '0').toFixed(2)}</td></tr>` : ''}
                ${parseFloat(selectedSale.card_amount?.toString() || '0') > 0 ? `<tr><td style="text-align: left; padding: 1px 2px; font-size: 12px;">Card:</td><td style="text-align: right; padding: 1px 2px; font-size: 12px;">Rs. ${parseFloat(selectedSale.card_amount?.toString() || '0').toFixed(2)}</td></tr>` : ''}
                <tr><td style="text-align: left; padding: 1px 2px; font-size: 12px;">Change:</td><td style="text-align: right; padding: 1px 2px; font-size: 12px;">Rs. ${((parseFloat(selectedSale.cash_amount?.toString() || '0') + parseFloat(selectedSale.card_amount?.toString() || '0')) - (parseFloat(selectedSale.total_amount.toString()) - parseFloat(selectedSale.discount_amount.toString()))).toFixed(2)}</td></tr>
              </table>
            </div>
            
            <div style="margin-top: 3px; text-align: center;">
              <p style="font-size: 12px; margin: 2px 0;">Thank you for your purchase!</p>
            </div>
          </div>
        </div>
        <script>
          window.onload = function() {
            window.print();
            window.close();
          };
        </script>
      </body>
      </html>
    `;

    printWindow.document.write(invoiceHTML);
    printWindow.document.close();
  };

  const closeInvoice = () => {
    setShowInvoiceModal(false);
    setSelectedSale(null);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        <div className="container mx-auto px-4 py-8">
          <div className="mb-8">
            <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
              <i className="fas fa-chart-bar mr-3"></i>Sales Reports
            </h1>
          </div>

          {/* Filter Controls Skeleton */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-8">
            <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded animate-pulse w-32 mb-4"></div>
            <div className="flex flex-wrap gap-4 items-end">
              <div className="h-10 w-32 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
              <div className="h-10 w-32 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
            </div>
          </div>

          {/* Statistics Cards Skeleton */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <StatsCardSkeleton />
            <StatsCardSkeleton />
            <StatsCardSkeleton />
            <StatsCardSkeleton />
          </div>

          {/* Sales Table Skeleton */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md">
            <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Sales Details</h2>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead className="bg-gray-50 dark:bg-gray-900">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cashier</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Items</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Amount</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Discount</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Final Amount</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payment Method</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                  {[...Array(8)].map((_, index) => (
                    <TableRowSkeleton key={index} />
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
      <div className="container mx-auto px-4 py-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
            <i className="fas fa-chart-bar mr-3"></i>Sales Reports
          </h1>
        </div>

        {/* Filter Controls */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-8">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Filter Reports</h2>
          <div className="flex flex-wrap gap-4 items-end">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Quick Filter</label>
              <select 
                value={filter}
                onChange={(e) => handleFilterChange(e.target.value)}
                className="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
              >
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="week">Last 7 Days</option>
                <option value="month">Last 30 Days</option>
                <option value="custom">Custom Range</option>
              </select>
            </div>
            
            {filter === 'custom' && (
              <div className="flex gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Date</label>
                  <input 
                    type="date" 
                    value={startDate}
                    onChange={(e) => setStartDate(e.target.value)}
                    className="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Date</label>
                  <input 
                    type="date" 
                    value={endDate}
                    onChange={(e) => setEndDate(e.target.value)}
                    className="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                <div>
                  <button 
                    onClick={fetchReports}
                    className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                  >
                    Apply Filter
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Statistics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {/* Total Sales */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-blue-500">
            <div className="flex items-center">
              <div className="flex-1">
                <p className="text-sm font-medium text-blue-600 uppercase tracking-wide">Total Sales</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{stats.total_sales}</p>
              </div>
              <div className="text-blue-500">
                <i className="fas fa-shopping-cart text-3xl"></i>
              </div>
            </div>
          </div>

          {/* Total Revenue */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <div className="flex items-center">
              <div className="flex-1">
                <p className="text-sm font-medium text-green-600 uppercase tracking-wide">Total Revenue</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                  Rs. {stats.total_amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </p>
              </div>
              <div className="text-green-500">
                <i className="fas fa-money-bill-wave text-3xl"></i>
              </div>
            </div>
          </div>

          {/* Total Items */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-indigo-500">
            <div className="flex items-center">
              <div className="flex-1">
                <p className="text-sm font-medium text-indigo-600 uppercase tracking-wide">Items Sold</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{stats.total_items}</p>
              </div>
              <div className="text-indigo-500">
                <i className="fas fa-box text-3xl"></i>
              </div>
            </div>
          </div>

          {/* Average Sale */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
            <div className="flex items-center">
              <div className="flex-1">
                <p className="text-sm font-medium text-yellow-600 uppercase tracking-wide">Avg Sale</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                  Rs. {stats.avg_sale.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </p>
              </div>
              <div className="text-yellow-500">
                <i className="fas fa-chart-line text-3xl"></i>
              </div>
            </div>
          </div>
        </div>

        {/* Sales Details Table */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md">
          <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Sales Details</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date & Time</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sale ID</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cashier</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Items</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Subtotal</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Discount</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Final Amount</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payment Method</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {sales.length > 0 ? (
                  sales.map((sale) => (
                    <tr key={sale.id}>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        {new Date(sale.date).toLocaleDateString('en-US', { 
                          month: 'short', 
                          day: 'numeric', 
                          year: 'numeric' 
                        })} {new Date(sale.date).toLocaleTimeString('en-US', { 
                          hour: '2-digit', 
                          minute: '2-digit' 
                        })}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        #{sale.id}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        {sale.cashier_name}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {sale.items_count}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        Rs. {sale.total_amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        Rs. {sale.discount_amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                        Rs. {sale.final_amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {sale.payment_method.toUpperCase()}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <button 
                          onClick={() => viewInvoice(sale.id)}
                          className="text-blue-600 hover:text-blue-900 font-medium"
                        >
                          <i className="fas fa-file-invoice mr-1"></i>View Invoice
                        </button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={9} className="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                      No sales found for the selected period.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Invoice Modal */}
      {showInvoiceModal && selectedSale && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) closeInvoice() }}>
          <div className="relative top-5 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <div className="mt-3">
              <div className="text-center" style={{ fontSize: '14px', lineHeight: '1.3', width: '100%', margin: 0, padding: '0 3mm' }}>
                <div style={{ marginBottom: '3px' }}>
                  <h2 style={{ fontSize: '18px', margin: 0 }}>INVOICE</h2>
                  <p style={{ fontSize: '12px', margin: '2px 0' }}>Sale #{selectedSale.id}</p>
                  <p style={{ fontSize: '12px', margin: '2px 0' }}>
                    {new Date(selectedSale.date).toLocaleDateString()} {new Date(selectedSale.date).toLocaleTimeString()}
                  </p>
                </div>
                
                <div style={{ marginBottom: '3px' }}>
                  <table style={{ width: '100%', fontSize: '12px', margin: '2px 0', tableLayout: 'fixed' }}>
                    <thead>
                      <tr style={{ borderBottom: '1px solid #ccc' }}>
                        <th style={{ padding: '2px', fontSize: '12px', width: '45%' }}>Item</th>
                        <th style={{ padding: '2px', fontSize: '12px', width: '15%' }}>Qty</th>
                        <th style={{ padding: '2px', fontSize: '12px', width: '20%' }}>Price</th>
                        <th style={{ padding: '2px', fontSize: '12px', width: '20%' }}>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      {selectedSale.items.map((item, index) => {
                        const price = parseFloat(item.price.toString());
                        const quantity = parseInt(item.quantity.toString());
                        const taxRate = parseFloat(item.tax_rate.toString()) || 0;
                        const itemTotal = price * quantity;
                        const itemSubtotal = taxRate > 0 ? (itemTotal / (1 + taxRate / 100)) : itemTotal;
                        const itemTax = itemTotal - itemSubtotal;
                        const displayName = (item as { product_name?: string; name?: string }).product_name ?? (item as { product_name?: string; name?: string }).name ?? '';
                        
                        return (
                          <React.Fragment key={index}>
                            <tr>
                              <td style={{ padding: '2px', fontSize: '12px', textAlign: 'left' }}>{displayName}</td>
                              <td style={{ padding: '2px', fontSize: '12px', textAlign: 'center' }}>{item.quantity}</td>
                              <td style={{ padding: '2px', fontSize: '12px', textAlign: 'right' }}>{price.toFixed(2)}</td>
                              <td style={{ padding: '2px', fontSize: '12px', textAlign: 'right' }}>{itemTotal.toFixed(2)}</td>
                            </tr>
                            <tr>
                              <td colSpan={4} style={{ padding: '1px 2px', fontSize: '10px', color: '#666', textAlign: 'right' }}>
                                Tax: Rs. {itemTax.toFixed(2)} ({taxRate}%)
                              </td>
                            </tr>
                            <tr style={{ height: '2px' }}>
                              <td colSpan={4} style={{ borderBottom: '1px solid #ddd', padding: 0 }}></td>
                            </tr>
                          </React.Fragment>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
                
                <div style={{ marginTop: '3px', borderTop: '1px solid #ccc' }}>
                  <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <tr>
                      <td style={{ textAlign: 'left', padding: '1px 2px', fontSize: '12px' }}>Subtotal:</td>
                      <td style={{ textAlign: 'right', padding: '1px 2px', fontSize: '12px' }}>
                        Rs. {parseFloat(selectedSale.subtotal_amount?.toString() || selectedSale.total_amount.toString()).toFixed(2)}
                      </td>
                    </tr>
                    <tr>
                      <td style={{ textAlign: 'left', padding: '1px 2px', fontSize: '12px' }}>Tax:</td>
                      <td style={{ textAlign: 'right', padding: '1px 2px', fontSize: '12px' }}>
                        Rs. {parseFloat(selectedSale.tax_amount?.toString() || '0').toFixed(2)}
                      </td>
                    </tr>
                    <tr>
                      <td style={{ textAlign: 'left', padding: '1px 2px', fontSize: '12px' }}>Discount:</td>
                      <td style={{ textAlign: 'right', padding: '1px 2px', fontSize: '12px' }}>
                        -Rs. {parseFloat(selectedSale.discount_amount.toString()).toFixed(2)}
                      </td>
                    </tr>
                    <tr>
                      <td style={{ textAlign: 'left', padding: '1px 2px', fontSize: '14px', fontWeight: 'bold' }}>Total:</td>
                      <td style={{ textAlign: 'right', padding: '1px 2px', fontSize: '14px', fontWeight: 'bold' }}>
                        Rs. {(parseFloat(selectedSale.total_amount.toString()) - parseFloat(selectedSale.discount_amount.toString())).toFixed(2)}
                      </td>
                    </tr>
                  </table>
                </div>
                
                <div style={{ marginTop: '3px', borderTop: '1px solid #ccc' }}>
                  <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <tr>
                      <td style={{ textAlign: 'left', padding: '1px 2px', fontSize: '12px' }}>Payment Method:</td>
                      <td style={{ textAlign: 'right', padding: '1px 2px', fontSize: '12px' }}>
                        {selectedSale.payment_method.toUpperCase()}
                      </td>
                    </tr>
                    {parseFloat(selectedSale.cash_amount?.toString() || '0') > 0 && (
                      <tr>
                        <td style={{ textAlign: 'left', padding: '1px 2px', fontSize: '12px' }}>Cash:</td>
                        <td style={{ textAlign: 'right', padding: '1px 2px', fontSize: '12px' }}>
                          Rs. {parseFloat(selectedSale.cash_amount?.toString() || '0').toFixed(2)}
                        </td>
                      </tr>
                    )}
                    {parseFloat(selectedSale.card_amount?.toString() || '0') > 0 && (
                      <tr>
                        <td style={{ textAlign: 'left', padding: '1px 2px', fontSize: '12px' }}>Card:</td>
                        <td style={{ textAlign: 'right', padding: '1px 2px', fontSize: '12px' }}>
                          Rs. {parseFloat(selectedSale.card_amount?.toString() || '0').toFixed(2)}
                        </td>
                      </tr>
                    )}
                    <tr>
                      <td style={{ textAlign: 'left', padding: '1px 2px', fontSize: '12px' }}>Change:</td>
                      <td style={{ textAlign: 'right', padding: '1px 2px', fontSize: '12px' }}>
                        Rs. {((parseFloat(selectedSale.cash_amount?.toString() || '0') + parseFloat(selectedSale.card_amount?.toString() || '0')) - (parseFloat(selectedSale.total_amount.toString()) - parseFloat(selectedSale.discount_amount.toString()))).toFixed(2)}
                      </td>
                    </tr>
                  </table>
                </div>
                
                <div style={{ marginTop: '3px', textAlign: 'center' }}>
                  <p style={{ fontSize: '12px', margin: '2px 0' }}>Thank you for your purchase!</p>
                </div>
              </div>
              
              <div className="flex justify-center space-x-4 mt-6">
                <button 
                  onClick={printInvoice}
                  className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                >
                  <i className="fas fa-print mr-2"></i>Print Invoice
                </button>
                <button 
                  onClick={closeInvoice}
                  className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                >
                  <i className="fas fa-times mr-2"></i>Close
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Reports;
