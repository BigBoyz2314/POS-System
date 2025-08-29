import React, { useState, useEffect, useRef } from 'react';
import { useAuth } from '../hooks/useAuth';
import api from '../services/api';

interface Purchase {
  id: number;
  purchase_date: string;
  vendor_name: string;
  total_amount: number;
  payment_method: string;
  user_name: string;
  notes: string;
}

interface PurchaseItem {
  id: number;
  product_name: string;
  quantity: number;
  cost_price: number;
}

interface PurchaseDetails {
  id: number;
  purchase_date: string;
  vendor_name: string;
  total_amount: number;
  payment_method: string;
  user_name: string;
  notes: string;
  items: PurchaseItem[];
}

interface Vendor {
  id: number;
  name: string;
}

interface Product {
  id: number;
  name: string;
  sku: string;
  cost_price: number;
  avg_cost_price: number;
  stock: number;
}

interface CartItem {
  id: number;
  name: string;
  cost_price: number;
  quantity: number;
  stock: number;
}

const Purchases: React.FC = () => {
  const { user } = useAuth();
  const [purchases, setPurchases] = useState<Purchase[]>([]);
  const [vendors, setVendors] = useState<Vendor[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
  const [showViewModal, setShowViewModal] = useState(false);
  const [showControlsModal, setShowControlsModal] = useState(false);
  const [selectedPurchase, setSelectedPurchase] = useState<PurchaseDetails | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [purchaseCart, setPurchaseCart] = useState<CartItem[]>([]);
  const [formData, setFormData] = useState({
    vendor_id: '',
    purchase_date: new Date().toISOString().split('T')[0],
    payment_method: 'cash',
    notes: ''
  });
  
  const searchInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    fetchData();
  }, []);

  useEffect(() => {
    // Keyboard shortcuts
    const handleKeyDown = (e: KeyboardEvent) => {
      if (!showAddModal) return;
      
      // Check if user is typing in a form field
      const activeElement = document.activeElement;
      if (activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA' || activeElement.tagName === 'SELECT')) {
        return;
      }

      // F2 or Ctrl+F to focus search
      if (e.key === 'F2' || ((e.ctrlKey || e.metaKey) && e.key === 'f')) {
        e.preventDefault();
        searchInputRef.current?.focus();
      }

      // +/- to adjust last item quantity
      if (e.key === '+' || e.key === '=') {
        e.preventDefault();
        adjustLastItemQuantity(1);
      }
      if (e.key === '-' || e.key === '_') {
        e.preventDefault();
        adjustLastItemQuantity(-1);
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [showAddModal, purchaseCart]);

  const fetchData = async () => {
    try {
      setLoading(true);
      
      // Fetch purchases
      const purchasesResponse = await api.get('purchases_management.php');
      if (purchasesResponse.data.success) {
        setPurchases(purchasesResponse.data.purchases);
      }

      // Fetch vendors
      const vendorsResponse = await api.get('vendors_management.php');
      if (vendorsResponse.data.success) {
        setVendors(vendorsResponse.data.vendors);
      }

      // Fetch products
      const productsResponse = await api.get('products_management.php');
      if (productsResponse.data.success) {
        setProducts(productsResponse.data.products);
      }
    } catch (error) {
      console.error('Error fetching data:', error);
      setError('Error loading data');
    } finally {
      setLoading(false);
    }
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const resetForm = () => {
    setFormData({
      vendor_id: '',
      purchase_date: new Date().toISOString().split('T')[0],
      payment_method: 'cash',
      notes: ''
    });
    setPurchaseCart([]);
  };

  const addToCart = (product: Product) => {
    setPurchaseCart(prev => {
      const existingItem = prev.find(item => item.id === product.id);
      if (existingItem) {
        return prev.map(item => 
          item.id === product.id 
            ? { ...item, quantity: item.quantity + 1 }
            : item
        );
      } else {
        const avgCostPrice = product.avg_cost_price || product.cost_price || 0;
        return [...prev, {
          id: product.id,
          name: product.name,
          cost_price: avgCostPrice,
          quantity: 1,
          stock: product.stock
        }];
      }
    });
  };

  const updateCartItemQuantity = (index: number, change: number) => {
    setPurchaseCart(prev => {
      const newCart = [...prev];
      const newQuantity = newCart[index].quantity + change;
      
      if (newQuantity <= 0) {
        newCart.splice(index, 1);
      } else {
        newCart[index].quantity = newQuantity;
      }
      
      return newCart;
    });
  };

  const updateCartItemCostPrice = (index: number, newPrice: number) => {
    setPurchaseCart(prev => {
      const newCart = [...prev];
      newCart[index].cost_price = newPrice;
      return newCart;
    });
  };

  const removeFromCart = (index: number) => {
    setPurchaseCart(prev => prev.filter((_, i) => i !== index));
  };

  const adjustLastItemQuantity = (change: number) => {
    if (purchaseCart.length === 0) return;
    updateCartItemQuantity(purchaseCart.length - 1, change);
  };

  const getCartTotal = () => {
    return purchaseCart.reduce((sum, item) => sum + (item.cost_price * item.quantity), 0);
  };

  const handleAddPurchase = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (purchaseCart.length === 0) {
      setError('Purchase cart is empty.');
      return;
    }

    if (!formData.vendor_id || !formData.purchase_date || !formData.payment_method) {
      setError('Please fill all required fields.');
      return;
    }

    try {
      const total = getCartTotal();
      const response = await api.post('purchases_management.php', {
        action: 'add',
        vendor_id: formData.vendor_id,
        purchase_date: formData.purchase_date,
        total_amount: total,
        payment_method: formData.payment_method,
        notes: formData.notes,
        items: purchaseCart
      });

      if (response.data.success) {
        setMessage('Purchase added successfully! Purchase ID: ' + response.data.purchase_id);
        setShowAddModal(false);
        resetForm();
        fetchData();
      } else {
        setError(response.data.message || 'Error adding purchase');
      }
    } catch (error) {
      console.error('Error adding purchase:', error);
      setError('Error adding purchase');
    }
  };

  const handleDeletePurchase = async (id: number) => {
    if (!window.confirm('Are you sure you want to delete this purchase? This will reverse the stock changes.')) {
      return;
    }

    try {
      const response = await api.post('purchases_management.php', {
        action: 'delete',
        id: id
      });

      if (response.data.success) {
        setMessage('Purchase deleted successfully.');
        fetchData();
      } else {
        setError(response.data.message || 'Error deleting purchase');
      }
    } catch (error) {
      console.error('Error deleting purchase:', error);
      setError('Error deleting purchase');
    }
  };

  const viewPurchase = async (id: number) => {
    try {
      const response = await api.get(`purchase_details.php?purchase_id=${id}`);
      if (response.data.success) {
        setSelectedPurchase(response.data.purchase);
        setShowViewModal(true);
      } else {
        setError('Error loading purchase details');
      }
    } catch (error) {
      console.error('Error loading purchase details:', error);
      setError('Error loading purchase details');
    }
  };

  const openAddModal = () => {
    resetForm();
    setShowAddModal(true);
  };

  const filteredProducts = products.filter(product =>
    product.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    product.sku.toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600 dark:text-gray-400">Loading purchases...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
      <div className="container mx-auto px-4 py-8">
        <div className="flex justify-between items-center mb-8">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
            <i className="fas fa-shopping-cart mr-3"></i>Purchase Management
          </h1>
          <button 
            onClick={openAddModal}
            className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
          >
            <i className="fas fa-plus mr-2"></i>Add New Purchase
          </button>
        </div>

        {message && (
          <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {message}
          </div>
        )}

        {error && (
          <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {error}
          </div>
        )}

        {/* Purchases Table */}
        <div className="bg-white dark:bg-gray-800 shadow-md rounded-lg overflow-hidden">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Vendor</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Amount</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payment Method</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Added By</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {purchases.length > 0 ? (
                  purchases.map((purchase) => (
                    <tr key={purchase.id}>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        {new Date(purchase.purchase_date).toLocaleDateString('en-US', { 
                          month: 'short', 
                          day: 'numeric', 
                          year: 'numeric' 
                        })}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        {purchase.vendor_name}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                        Rs. {purchase.total_amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {purchase.payment_method}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {purchase.user_name}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button 
                          onClick={() => viewPurchase(purchase.id)}
                          className="text-blue-600 hover:text-blue-900 mr-3"
                        >
                          <i className="fas fa-eye mr-1"></i>View
                        </button>
                        <button 
                          onClick={() => handleDeletePurchase(purchase.id)}
                          className="text-red-600 hover:text-red-900"
                        >
                          <i className="fas fa-trash mr-1"></i>Delete
                        </button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={6} className="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                      No purchases found
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Add Purchase Modal */}
      {showAddModal && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setShowAddModal(false) }}>
          <div className="relative top-5 mx-auto p-4 sm:p-5 border w-full max-w-6xl shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <div className="mt-3">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                  <i className="fas fa-plus mr-2"></i>Add New Purchase
                </h3>
                <button 
                  className="bg-gray-500 hover:bg-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500 text-white p-2 rounded text-sm" 
                  title="Show Controls" 
                  onClick={() => setShowControlsModal(true)}
                >
                  <i className="fas fa-keyboard"></i>
                </button>
              </div>
              
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                {/* Product Search and Selection */}
                <div className="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                  <h4 className="text-md font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    <i className="fas fa-search mr-2"></i>Product Search
                  </h4>
                  
                  <div className="mb-3 sm:mb-4">
                    <div className="relative">
                      <i className="fas fa-search absolute left-2 sm:left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                      <input 
                        ref={searchInputRef}
                        type="text" 
                        placeholder="Search by product name or SKU..." 
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="w-full pl-8 sm:pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                      />
                    </div>
                  </div>
                  
                  <div className="overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-2 h-64">
                    {filteredProducts.length > 0 ? (
                      <div className="grid grid-cols-1 gap-2">
                        {filteredProducts.map((product) => (
                          <button
                            key={product.id}
                            onClick={() => addToCart(product)}
                            className="p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:shadow cursor-pointer bg-white dark:bg-gray-800 text-left"
                          >
                            <div className="flex flex-col gap-1">
                              <h3 className="font-medium text-gray-900 dark:text-gray-100 text-sm line-clamp-2">{product.name}</h3>
                              <div className="text-xs text-gray-500 dark:text-gray-400">SKU: {product.sku}</div>
                              <div className="text-xs text-gray-500 dark:text-gray-400">Stock: {product.stock}</div>
                            </div>
                            <div className="flex items-center justify-between mt-2">
                              <div className="font-semibold text-gray-900 dark:text-gray-100 text-sm">
                                Rs. {(product.avg_cost_price || product.cost_price || 0).toFixed(2)}
                              </div>
                            </div>
                          </button>
                        ))}
                      </div>
                    ) : (
                      <p className="text-gray-500 dark:text-gray-400 text-center py-8">No products found</p>
                    )}
                  </div>
                </div>

                {/* Purchase Cart */}
                <div className="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                  <h4 className="text-md font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    <i className="fas fa-shopping-basket mr-2"></i>Purchase Cart
                  </h4>
                  
                  <div className="overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-2 h-64 mb-4">
                    {purchaseCart.length > 0 ? (
                      <table className="w-full text-sm table-fixed">
                        <thead className="bg-gray-100 dark:bg-gray-900">
                          <tr>
                            <th className="text-left py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-1/2">Item</th>
                            <th className="text-center py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-16">Qty</th>
                            <th className="text-right py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-24">Cost Price</th>
                            <th className="text-right py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-28">Total</th>
                            <th className="text-center py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-10">Action</th>
                          </tr>
                        </thead>
                        <tbody className="bg-white dark:bg-gray-800">
                          {purchaseCart.map((item, index) => {
                            const total = item.cost_price * item.quantity;
                            return (
                              <tr key={index} className="border-b border-gray-200 dark:border-gray-700">
                                <td className="py-1 px-2 text-gray-900 dark:text-gray-100 whitespace-nowrap w-1/2">{item.name}</td>
                                <td className="py-1 px-2 text-center text-gray-900 dark:text-gray-100 whitespace-nowrap w-16">
                                  <button 
                                    onClick={() => updateCartItemQuantity(index, -1)} 
                                    className="bg-red-500 hover:bg-red-700 text-white p-0.5 rounded text-xs mr-1" 
                                    title="Decrease"
                                  >
                                    <i className="fas fa-minus text-xs"></i>
                                  </button>
                                  <span className="mx-1 align-middle text-xs">{item.quantity}</span>
                                  <button 
                                    onClick={() => updateCartItemQuantity(index, 1)} 
                                    className="bg-green-500 hover:bg-green-700 text-white p-0.5 rounded text-xs ml-1" 
                                    title="Increase"
                                  >
                                    <i className="fas fa-plus text-xs"></i>
                                  </button>
                                </td>
                                <td className="py-1 px-2 text-right whitespace-nowrap w-24">
                                  <input 
                                    type="number" 
                                    step="0.01" 
                                    min="0" 
                                    value={item.cost_price.toFixed(2)} 
                                    onChange={(e) => updateCartItemCostPrice(index, parseFloat(e.target.value) || 0)} 
                                    className="w-20 text-right border border-gray-300 dark:border-gray-700 rounded px-1 py-1 text-xs bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100"
                                  />
                                </td>
                                <td className="py-1 px-2 text-right font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap w-28">
                                  Rs. {total.toFixed(2)}
                                </td>
                                <td className="py-1 px-2 text-center whitespace-nowrap w-10">
                                  <button 
                                    onClick={() => removeFromCart(index)} 
                                    className="text-red-600 hover:text-red-900 text-xs"
                                  >
                                    <i className="fas fa-trash"></i>
                                  </button>
                                </td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    ) : (
                      <p className="text-gray-500 dark:text-gray-400 text-center py-8">Cart is empty</p>
                    )}
                  </div>
                  
                  <div className="border-t dark:border-gray-700 pt-2">
                    <div className="flex justify-between items-center mb-2">
                      <span className="text-base font-semibold text-gray-900 dark:text-gray-100">Total Amount:</span>
                      <span className="text-xl font-bold text-blue-600">Rs. {getCartTotal().toFixed(2)}</span>
                    </div>
                  </div>
                </div>
              </div>
              
              {/* Purchase Details Form */}
              <div className="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vendor *</label>
                  <select 
                    name="vendor_id" 
                    value={formData.vendor_id}
                    onChange={handleInputChange}
                    required 
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  >
                    <option value="">Select Vendor</option>
                    {vendors.map((vendor) => (
                      <option key={vendor.id} value={vendor.id}>{vendor.name}</option>
                    ))}
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Purchase Date *</label>
                  <input 
                    type="date" 
                    name="purchase_date"
                    value={formData.purchase_date}
                    onChange={handleInputChange}
                    required 
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Payment Method *</label>
                  <select 
                    name="payment_method"
                    value={formData.payment_method}
                    onChange={handleInputChange}
                    required 
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  >
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="check">Check</option>
                    <option value="credit">Credit</option>
                  </select>
                </div>
              </div>
              
              <div className="mt-4">
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
                <textarea 
                  name="notes"
                  value={formData.notes}
                  onChange={handleInputChange}
                  rows={3} 
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                />
              </div>
              
              <div className="flex justify-end space-x-4 mt-6">
                <button 
                  onClick={() => setShowAddModal(false)}
                  className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                >
                  <i className="fas fa-times mr-2"></i>Cancel
                </button>
                <button 
                  onClick={handleAddPurchase}
                  disabled={purchaseCart.length === 0}
                  className="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-bold py-2 px-4 rounded"
                >
                  <i className="fas fa-check mr-2"></i>Complete Purchase
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* View Purchase Modal */}
      {showViewModal && selectedPurchase && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setShowViewModal(false) }}>
          <div className="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <div className="mt-3">
              <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Purchase Details</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                  <h4 className="font-semibold text-gray-900 dark:text-gray-100 mb-2">Purchase Information</h4>
                  <p><strong>Purchase ID:</strong> {selectedPurchase.id}</p>
                  <p><strong>Date:</strong> {selectedPurchase.purchase_date}</p>
                  <p><strong>Vendor:</strong> {selectedPurchase.vendor_name}</p>
                  <p><strong>Payment Method:</strong> {selectedPurchase.payment_method}</p>
                  <p><strong>Added By:</strong> {selectedPurchase.user_name}</p>
                </div>
                <div>
                  <h4 className="font-semibold text-gray-900 dark:text-gray-100 mb-2">Notes</h4>
                  <p className="text-gray-600 dark:text-gray-300">{selectedPurchase.notes || 'No notes'}</p>
                </div>
              </div>
              
              <div className="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                <h4 className="font-semibold text-gray-900 dark:text-gray-100 mb-4">Purchase Items</h4>
                <table className="w-full">
                  <thead className="bg-gray-100 dark:bg-gray-900">
                    <tr>
                      <th className="text-left py-2 px-4 font-medium text-gray-700 dark:text-gray-300">Item</th>
                      <th className="text-center py-2 px-4 font-medium text-gray-700 dark:text-gray-300">Quantity</th>
                      <th className="text-right py-2 px-4 font-medium text-gray-700 dark:text-gray-300">Cost Price</th>
                      <th className="text-right py-2 px-4 font-medium text-gray-700 dark:text-gray-300">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {selectedPurchase.items.map((item, index) => {
                      const total = item.cost_price * item.quantity;
                      return (
                        <tr key={index} className="border-b border-gray-200 dark:border-gray-700">
                          <td className="py-2 px-4 text-sm text-gray-900 dark:text-gray-100">{item.product_name}</td>
                          <td className="py-2 px-4 text-sm text-center text-gray-900 dark:text-gray-100">{item.quantity}</td>
                                          <td className="py-2 px-4 text-sm text-right text-gray-900 dark:text-gray-100">Rs. {item.cost_price.toFixed(2)}</td>
                <td className="py-2 px-4 text-sm text-right font-medium text-gray-900 dark:text-gray-100">Rs. {total.toFixed(2)}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                  <tfoot className="bg-gray-100 dark:bg-gray-900">
                    <tr>
                      <td colSpan={3} className="py-2 px-4 text-right font-semibold text-gray-700 dark:text-gray-300">Total:</td>
                      <td className="py-2 px-4 text-right font-bold text-gray-900 dark:text-gray-100">
                        Rs. {selectedPurchase.total_amount.toFixed(2)}
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              
              <div className="flex justify-end mt-6">
                <button 
                  onClick={() => setShowViewModal(false)}
                  className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                >
                  <i className="fas fa-times mr-2"></i>Close
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Controls Modal */}
      {showControlsModal && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setShowControlsModal(false) }}>
          <div className="relative top-10 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">
              <i className="fas fa-keyboard mr-2"></i>Purchase Controls
            </h3>
            <ul className="text-sm space-y-2">
              <li><span className="font-semibold">F2 / Ctrl+F</span> – Focus Search</li>
              <li><span className="font-semibold">+</span> – Increase last item qty</li>
              <li><span className="font-semibold">-</span> – Decrease last item qty</li>
            </ul>
            <div className="flex justify-end mt-4">
              <button 
                className="bg-gray-500 hover:bg-gray-700 text-white py-2 px-4 rounded" 
                onClick={() => setShowControlsModal(false)}
              >
                <i className="fas fa-times mr-1"></i>Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Purchases;
