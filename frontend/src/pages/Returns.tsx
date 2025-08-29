import React, { useState, useEffect } from 'react';
import { useAuth } from '../hooks/useAuth';
import api from '../services/api';
import { TableRowSkeleton } from '../components/Skeleton';

interface ReturnItem {
  id: number;
  sale_id: number;
  product_id: number;
  product_name: string;
  quantity: number;
  reason: string;
  refund_amount: number;
  price: number;
  total_price: number;
  created_at: string;
}

interface SaleReturn {
  sale_id: number;
  total_refund: number;
  items: ReturnItem[];
  created_at: string;
}



const Returns: React.FC = () => {
  const { isAuthenticated, loading: authLoading } = useAuth();

  const [sales, setSales] = useState<SaleReturn[]>([]);
  const [receipts, setReceipts] = useState<{[key: number]: number}>({});
  const [loading, setLoading] = useState(true);
  const [selectedSaleId, setSelectedSaleId] = useState<number | null>(null);
  const [showReceiptModal, setShowReceiptModal] = useState(false);
  const [selectedReceipt, setSelectedReceipt] = useState<any>(null);
  const [receiptLoading, setReceiptLoading] = useState(false);
  const [expandedSales, setExpandedSales] = useState<{[key: number]: boolean}>({});

  useEffect(() => {
    if (!authLoading && isAuthenticated) {
      fetchReturns();
    }
  }, [authLoading, isAuthenticated]);

  const fetchReturns = async (saleId?: number) => {
    try {
      setLoading(true);
      const params = saleId ? `?sale_id=${saleId}` : '';
      const response = await api.get(`list_returns.php${params}`);
      
      if (response.data.success) {

        setSales(response.data.sales || []);
        setReceipts(response.data.receipts || {});
      }
    } catch (error) {
      console.error('Error fetching returns:', error);
    } finally {
      setLoading(false);
    }
  };

  const toggleSaleExpansion = (saleId: number) => {
    setExpandedSales(prev => ({
      ...prev,
      [saleId]: !prev[saleId]
    }));
  };

  const viewReceipt = async (receiptId: number) => {
    try {
      setReceiptLoading(true);
      const response = await api.get(`return_receipt.php?id=${receiptId}`);
      
      if (response.data.success) {
        setSelectedReceipt({
          ...response.data.receipt,
          sale_id: response.data.sale_id,
          receipt_id: receiptId
        });
        setShowReceiptModal(true);
      }
    } catch (error) {
      console.error('Error fetching receipt:', error);
    } finally {
      setReceiptLoading(false);
    }
  };

  const printReceipt = () => {
    if (!selectedReceipt) return;

    const printWindow = window.open('', '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes');
    
    if (!printWindow) {
      alert('Popup blocked! Please allow popups for this site and try again.');
      return;
    }

    const receiptHTML = `
      <!DOCTYPE html>
      <html>
      <head>
        <title>Return Receipt</title>
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
          th:nth-child(1), td:nth-child(1) { width: 40%; }
          th:nth-child(2), td:nth-child(2) { width: 15%; text-align: center; }
          th:nth-child(3), td:nth-child(3) { width: 22%; text-align: right; white-space: nowrap; }
          th:nth-child(4), td:nth-child(4) { width: 23%; text-align: right; white-space: nowrap; }
          .text-center { text-align: center; }
          .text-right { text-align: right; }
          .border-t { border-top: 1px solid #ccc; }
          .font-bold { font-weight: bold; }
          @media print { 
            body { margin: 0; padding: 2mm; width: 76mm; } 
            @page { margin: 0; size: 80mm auto; } 
          }
        </style>
      </head>
      <body>
        <div style="font-size: 14px; line-height: 1.3; width: 100%; margin: 0; padding: 0 3mm;">
          <div style="margin-bottom: 3px; text-align: center;">
            <h2 style="font-size: 18px; margin: 0; font-weight: bold;">RETURN RECEIPT</h2>
            <p style="font-size: 12px; margin: 2px 0;">Return #${selectedReceipt.receipt_id || 'N/A'}</p>
            <p style="font-size: 12px; margin: 2px 0;">Original Sale #${selectedReceipt.sale_id || 'N/A'}</p>
            <p style="font-size: 12px; margin: 2px 0;">${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
          </div>
          
          <div style="margin-bottom: 3px;">
            <table>
              <thead>
                <tr style="border-bottom: 1px solid #ccc;">
                  <th style="padding: 2px; font-size: 12px; width: 40%;">Item</th>
                  <th style="padding: 2px; font-size: 12px; width: 15%; text-align: center;">Qty</th>
                  <th style="padding: 2px; font-size: 12px; width: 22%; text-align: right;">Price</th>
                  <th style="padding: 2px; font-size: 12px; width: 23%; text-align: right;">Refund</th>
                </tr>
              </thead>
              <tbody>
                ${selectedReceipt.items ? selectedReceipt.items.map((item: any) => `
                  <tr>
                    <td style="padding: 2px; font-size: 12px;">${item.product_name || item.name || 'Unknown'}</td>
                    <td style="padding: 2px; font-size: 12px; text-align: center;">${item.return_qty || item.quantity || 0}</td>
                    <td style="padding: 2px; font-size: 12px; text-align: right;">Rs. ${(item.price || 0).toFixed(2)}</td>
                    <td style="padding: 2px; font-size: 12px; text-align: right;">Rs. ${(item.refund_amount || 0).toFixed(2)}</td>
                  </tr>
                  <tr>
                    <td colspan="4" style="padding: 1px 2px; font-size: 10px; color: #666; text-align: right;">
                      Reason: ${item.reason || 'N/A'}
                    </td>
                  </tr>
                  <tr style="height: 2px;">
                    <td colspan="4" style="border-bottom: 1px solid #ddd; padding: 0;"></td>
                  </tr>
                `).join('') : ''}
              </tbody>
            </table>
          </div>
          
          <div style="margin-top: 3px; border-top: 1px solid #ccc;">
            <table style="width: 100%; border-collapse: collapse;">
              <tr>
                <td style="text-align: left; padding: 1px 2px; font-size: 12px;">Total Refund:</td>
                <td style="text-align: right; padding: 1px 2px; font-size: 14px; font-weight: bold;">
                  Rs. ${(selectedReceipt.total_refund || 0).toFixed(2)}
                </td>
              </tr>
              ${selectedReceipt.cash_refund > 0 ? `
                <tr>
                  <td style="text-align: left; padding: 1px 2px; font-size: 12px;">Cash Refund:</td>
                  <td style="text-align: right; padding: 1px 2px; font-size: 12px;">
                    Rs. ${selectedReceipt.cash_refund.toFixed(2)}
                  </td>
                </tr>
              ` : ''}
              ${selectedReceipt.card_refund > 0 ? `
                <tr>
                  <td style="text-align: left; padding: 1px 2px; font-size: 12px;">Card Refund:</td>
                  <td style="text-align: right; padding: 1px 2px; font-size: 12px;">
                    Rs. ${selectedReceipt.card_refund.toFixed(2)}
                  </td>
                </tr>
              ` : ''}
            </table>
          </div>
          
          <div style="margin-top: 5px; text-align: center; font-size: 10px; color: #666;">
            <p>Thank you for your business!</p>
            <p>Return processed on ${new Date().toLocaleDateString()}</p>
          </div>
        </div>
      </body>
      </html>
    `;

    printWindow.document.write(receiptHTML);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        <div className="container mx-auto px-4 py-8">
          <div className="mb-8">
            <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
              <i className="fas fa-undo mr-3"></i>Returns Management
            </h1>
          </div>

          {/* Returns Table Skeleton */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md">
            <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Returns List</h2>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead className="bg-gray-50 dark:bg-gray-900">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sale ID</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Refund</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Items</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
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
            <i className="fas fa-undo mr-3"></i>Returns Management
          </h1>
        </div>

        {/* Filter Controls */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-8">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Filter Returns</h2>
          <div className="flex flex-wrap gap-4 items-end">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sale ID Filter</label>
              <input 
                type="number" 
                placeholder="Enter Sale ID to filter"
                value={selectedSaleId || ''}
                onChange={(e) => setSelectedSaleId(e.target.value ? Number(e.target.value) : null)}
                className="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
              />
            </div>
            <div className="flex gap-2">
              <button 
                onClick={() => fetchReturns(selectedSaleId || undefined)}
                className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
              >
                <i className="fas fa-search mr-2"></i>Filter
              </button>
              <button 
                onClick={() => {
                  setSelectedSaleId(null);
                  fetchReturns();
                }}
                className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
              >
                <i className="fas fa-times mr-2"></i>Clear
              </button>
            </div>
          </div>
        </div>

        {/* Returns Table */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md">
          <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Returns by Sale</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sale ID</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Refund</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Items</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {sales.length > 0 ? (
                  sales.map((sale) => (
                    <React.Fragment key={sale.sale_id}>
                      <tr className="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                          {sale.sale_id}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                          Rs. {sale.total_refund.toFixed(2)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                          <button 
                            onClick={() => toggleSaleExpansion(sale.sale_id)}
                            className="text-blue-600 hover:text-blue-900 flex items-center"
                          >
                            <i className={`fas fa-chevron-${expandedSales[sale.sale_id] ? 'up' : 'down'} mr-2`}></i>
                            {sale.items.length} item{sale.items.length !== 1 ? 's' : ''}
                          </button>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                          {new Date(sale.created_at).toLocaleDateString()}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                          {receipts[sale.sale_id] && (
                            <button 
                              onClick={() => viewReceipt(receipts[sale.sale_id])}
                              className="text-blue-600 hover:text-blue-900 mr-3"
                              disabled={receiptLoading}
                            >
                              <i className="fas fa-print mr-1"></i>Receipt
                            </button>
                          )}
                        </td>
                      </tr>
                      {expandedSales[sale.sale_id] && (
                        <tr>
                          <td colSpan={5} className="px-6 py-0">
                            <div className="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
                              <h4 className="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Returned Items:</h4>
                              <div className="space-y-2">
                                {sale.items.map((item) => (
                                  <div key={item.id} className="flex justify-between items-center p-3 bg-white dark:bg-gray-800 rounded border">
                                    <div className="flex-1">
                                      <div className="font-medium text-gray-900 dark:text-gray-100">{item.product_name}</div>
                                      <div className="text-sm text-gray-500 dark:text-gray-400">
                                        Qty: {item.quantity} | Price: Rs. {item.price.toFixed(2)} | Total: Rs. {item.total_price.toFixed(2)}
                                      </div>
                                      <div className="text-sm text-gray-500 dark:text-gray-400">
                                        Reason: {item.reason}
                                      </div>
                                    </div>
                                    <div className="text-right">
                                      <div className="font-medium text-red-600 dark:text-red-400">
                                        Refund: Rs. {item.refund_amount.toFixed(2)}
                                      </div>
                                    </div>
                                  </div>
                                ))}
                              </div>
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  ))
                ) : (
                  <tr>
                    <td colSpan={5} className="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                      No returns found
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Receipt Modal */}
      {showReceiptModal && selectedReceipt && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setShowReceiptModal(false) }}>
          <div className="relative top-10 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <div className="text-left" style={{fontSize: '14px', lineHeight: '1.3', width: '100%', margin: 0, padding: '0 3mm'}}>
              <div className="text-center mb-1" style={{marginBottom: '3px'}}>
                <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100" style={{fontSize: '18px', margin: 0}}>RETURN RECEIPT</h2>
                <p className="text-xs text-gray-600 dark:text-gray-400" style={{fontSize: '12px', margin: '2px 0'}}>Return #${selectedReceipt.receipt_id || 'N/A'}</p>
                <p className="text-xs text-gray-600 dark:text-gray-400" style={{fontSize: '12px', margin: '2px 0'}}>Original Sale #${selectedReceipt.sale_id || 'N/A'}</p>
                <p className="text-xs text-gray-600 dark:text-gray-400" style={{fontSize: '12px', margin: '2px 0'}}>{new Date().toLocaleDateString()} {new Date().toLocaleTimeString()}</p>
              </div>
              
              <div className="mb-1" style={{marginBottom: '3px'}}>
                <table className="w-full text-xs" style={{fontSize: '12px', margin: '2px 0', tableLayout: 'fixed'}}>
                  <thead>
                    <tr className="border-b border-gray-300 dark:border-gray-600">
                      <th className="py-1 text-left text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px', width: '45%'}}>Item</th>
                      <th className="py-1 text-center text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px', width: '15%'}}>Qty</th>
                      <th className="py-1 text-right text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px', width: '20%'}}>Price</th>
                      <th className="py-1 text-right text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px', width: '20%'}}>Refund</th>
                    </tr>
                  </thead>
                  <tbody>
                    {selectedReceipt.items ? selectedReceipt.items.map((item: any, index: number) => (
                      <React.Fragment key={index}>
                        <tr>
                          <td className="py-1 text-left text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px'}}>{item.product_name || item.name || 'Unknown'}</td>
                          <td className="py-1 text-center text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px'}}>{item.return_qty || item.quantity || 0}</td>
                          <td className="py-1 text-right text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px'}}>Rs. {(item.price || 0).toFixed(2)}</td>
                          <td className="py-1 text-right text-gray-900 dark:text-gray-100" style={{padding: '2px', fontSize: '12px'}}>Rs. {(item.refund_amount || 0).toFixed(2)}</td>
                        </tr>
                        <tr>
                          <td colSpan={4} className="py-0 text-right text-gray-600 dark:text-gray-400" style={{padding: '1px 2px', fontSize: '10px'}}>
                            Reason: {item.reason || 'N/A'}
                          </td>
                        </tr>
                        <tr style={{height: '2px'}}>
                          <td colSpan={4} style={{borderBottom: '1px solid #ddd', padding: 0}}></td>
                        </tr>
                      </React.Fragment>
                    )) : null}
                  </tbody>
                </table>
              </div>
              
              <div className="mt-1" style={{marginTop: '3px', borderTop: '1px solid #ccc'}}>
                <table className="w-full" style={{width: '100%', borderCollapse: 'collapse'}}>
                  <tr>
                    <td className="text-left py-1 px-2 text-gray-900 dark:text-gray-100" style={{textAlign: 'left', padding: '1px 2px', fontSize: '12px'}}>Total Refund:</td>
                    <td className="text-right py-1 px-2 font-bold text-gray-900 dark:text-gray-100" style={{textAlign: 'right', padding: '1px 2px', fontSize: '14px', fontWeight: 'bold'}}>
                      Rs. {(selectedReceipt.total_refund || 0).toFixed(2)}
                    </td>
                  </tr>
                  {selectedReceipt.cash_refund > 0 && (
                    <tr>
                      <td className="text-left py-1 px-2 text-gray-900 dark:text-gray-100" style={{textAlign: 'left', padding: '1px 2px', fontSize: '12px'}}>Cash Refund:</td>
                      <td className="text-right py-1 px-2 text-gray-900 dark:text-gray-100" style={{textAlign: 'right', padding: '1px 2px', fontSize: '12px'}}>
                        Rs. {selectedReceipt.cash_refund.toFixed(2)}
                      </td>
                    </tr>
                  )}
                  {selectedReceipt.card_refund > 0 && (
                    <tr>
                      <td className="text-left py-1 px-2 text-gray-900 dark:text-gray-100" style={{textAlign: 'left', padding: '1px 2px', fontSize: '12px'}}>Card Refund:</td>
                      <td className="text-right py-1 px-2 text-gray-900 dark:text-gray-100" style={{textAlign: 'right', padding: '1px 2px', fontSize: '12px'}}>
                        Rs. {selectedReceipt.card_refund.toFixed(2)}
                      </td>
                    </tr>
                  )}
                </table>
              </div>
              
              <div className="mt-2 text-center text-gray-600 dark:text-gray-400" style={{marginTop: '5px', textAlign: 'center', fontSize: '10px', color: '#666'}}>
                <p>Thank you for your business!</p>
                <p>Return processed on {new Date().toLocaleDateString()}</p>
              </div>
            </div>
            
            <div className="flex justify-end gap-2 mt-4">
              <button 
                className="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded"
                onClick={() => setShowReceiptModal(false)}
              >
                <i className="fas fa-times mr-1"></i>Close
              </button>
              <button 
                className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded"
                onClick={printReceipt}
              >
                <i className="fas fa-print mr-1"></i>Print
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Returns;
