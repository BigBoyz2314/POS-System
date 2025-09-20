import React, { useState, useEffect } from 'react';
import { useAuth } from '../hooks/useAuth';
import api from '../services/api';
import { TableRowSkeleton } from '../components/Skeleton';
import { useConfirm } from '../contexts/ConfirmContext';

interface Product {
  id: number;
  name: string;
  sku: string;
  barcode?: string;
  category_name: string;
  price: number;
  tax_rate: number;
  avg_cost_price: number;
  stock: number;
  category_id: number;
  is_published?: number;
  featured?: number;
}

interface Category {
  id: number;
  name: string;
}

const Products: React.FC = () => {
  const { } = useAuth();
  const { confirm } = useConfirm();
  const [products, setProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [editingProduct, setEditingProduct] = useState<Product | null>(null);
  const [formData, setFormData] = useState({
    name: '',
    sku: '',
    barcode: '',
    category_id: '',
    price: '',
    cost_price: '0.00',
    tax_rate: '0.00',
    stock: ''
  });

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      setLoading(true);
      
      // Fetch products
      const productsResponse = await api.get('products_management.php');
      if (productsResponse.data.success) {
        setProducts(productsResponse.data.products);
      }

      // Fetch categories
      const categoriesResponse = await api.get('categories.php');
      if (categoriesResponse.data.success) {
        setCategories(categoriesResponse.data.categories);
      }
    } catch (error) {
      console.error('Error fetching data:', error);
      setError('Error loading products');
    } finally {
      setLoading(false);
    }
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const resetForm = () => {
    setFormData({
      name: '',
      sku: '',
      barcode: '',
      category_id: '',
      price: '',
      cost_price: '0.00',
      tax_rate: '0.00',
      stock: ''
    });
  };

  const handleAddProduct = async (e: React.FormEvent) => {
    e.preventDefault();
    
    try {
      const response = await api.post('products_management.php', {
        action: 'add',
        ...formData
      });

      if (response.data.success) {
        setMessage('Product added successfully.');
        setShowAddModal(false);
        resetForm();
        fetchData();
      } else {
        setError(response.data.message || 'Error adding product');
      }
    } catch (error) {
      console.error('Error adding product:', error);
      setError('Error adding product');
    }
  };

  const handleEditProduct = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!editingProduct) return;

    try {
      const response = await api.post('products_management.php', {
        action: 'edit',
        id: editingProduct.id,
        ...formData
      });

      if (response.data.success) {
        setMessage('Product updated successfully.');
        setShowEditModal(false);
        setEditingProduct(null);
        resetForm();
        fetchData();
      } else {
        setError(response.data.message || 'Error updating product');
      }
    } catch (error) {
      console.error('Error updating product:', error);
      setError('Error updating product');
    }
  };

  const handleDeleteProduct = async (id: number) => {
    const ok = await confirm({
      title: 'Delete product?',
      message: 'This action cannot be undone.',
      confirmText: 'Delete',
      danger: true,
    });
    if (!ok) return;

    try {
      const response = await api.post('products_management.php', {
        action: 'delete',
        id: id
      });

      if (response.data.success) {
        setMessage('Product deleted successfully.');
        fetchData();
      } else {
        setError(response.data.message || 'Error deleting product');
      }
    } catch (error) {
      console.error('Error deleting product:', error);
      setError('Error deleting product');
    }
  };

  const openEditModal = (product: Product) => {
    setEditingProduct(product);
    setFormData({
      name: product.name,
      sku: product.sku,
      barcode: product.barcode || '',
      category_id: product.category_id.toString(),
      price: product.price.toString(),
      cost_price: '0.00',
      tax_rate: product.tax_rate.toString(),
      stock: product.stock.toString()
    });
    setShowEditModal(true);
  };

  const openAddModal = () => {
    resetForm();
    setShowAddModal(true);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        <div className="container mx-auto px-4 py-8">
          <div className="flex justify-between items-center mb-8">
            <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
              <i className="fas fa-box mr-3"></i>Product Management
            </h1>
            <div className="h-10 w-32 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
          </div>

          {/* Products Table Skeleton */}
          <div className="bg-white dark:bg-gray-800 shadow-md rounded-lg overflow-hidden">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead className="bg-gray-50 dark:bg-gray-900">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SKU</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tax Rate</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cost Price</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stock</th>
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
        <div className="flex justify-between items-center mb-8">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
            <i className="fas fa-box mr-3"></i>Product Management
          </h1>
          <button 
            onClick={openAddModal}
            className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
          >
            <i className="fas fa-plus mr-2"></i>Add New Product
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

        {/* Products Table */}
        <div className="bg-white dark:bg-gray-800 shadow-md rounded-lg overflow-hidden">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SKU</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tax Rate</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cost Price</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stock</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Web</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {products.length > 0 ? (
                  products.map((product) => (
                    <tr key={product.id}>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                        {product.name}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {product.sku}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {product.category_name}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        Rs. {product.price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {product.tax_rate.toFixed(2)}%
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        Rs. {product.avg_cost_price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        <span className={product.stock < 10 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-900 dark:text-gray-100'}>
                          {product.stock}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        <label className="inline-flex items-center mr-4">
                          <input type="checkbox" className="mr-2" checked={!!product.is_published} onChange={async (e) => {
                            try {
                              await api.post('products_management.php', { action: 'set_flags', id: product.id, is_published: e.target.checked ? 1 : 0 })
                              fetchData()
                            } catch {}
                          }} />
                          <span>Show</span>
                        </label>
                        <label className="inline-flex items-center">
                          <input type="checkbox" className="mr-2" checked={!!product.featured} onChange={async (e) => {
                            try {
                              await api.post('products_management.php', { action: 'set_flags', id: product.id, featured: e.target.checked ? 1 : 0 })
                              fetchData()
                            } catch {}
                          }} />
                          <span>Featured</span>
                        </label>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button 
                          onClick={() => openEditModal(product)}
                          className="text-indigo-600 hover:text-indigo-900 mr-3"
                        >
                          <i className="fas fa-edit mr-1"></i>Edit
                        </button>
                        <button 
                          onClick={() => handleDeleteProduct(product.id)}
                          className="text-red-600 hover:text-red-900"
                        >
                          <i className="fas fa-trash mr-1"></i>Delete
                        </button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={8} className="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                      No products found
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Add Product Modal */}
      {showAddModal && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setShowAddModal(false) }}>
          <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <div className="mt-3">
              <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Add New Product</h3>
              <form onSubmit={handleAddProduct}>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                  <input 
                    type="text" 
                    name="name" 
                    value={formData.name}
                    onChange={handleInputChange}
                    required 
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">SKU</label>
                  <input 
                    type="text" 
                    name="sku" 
                    value={formData.sku}
                    onChange={handleInputChange}
                    required 
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Barcode (optional)</label>
                  <input 
                    type="text" 
                    name="barcode" 
                    value={formData.barcode}
                    onChange={handleInputChange}
                    placeholder="Scan or enter barcode"
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Category</label>
                  <select 
                    name="category_id" 
                    value={formData.category_id}
                    onChange={handleInputChange}
                    required 
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  >
                    <option value="">Select Category</option>
                    {categories.map((category) => (
                      <option key={category.id} value={category.id}>{category.name}</option>
                    ))}
                  </select>
                </div>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Price</label>
                  <input 
                    type="number" 
                    name="price" 
                    step="0.01" 
                    value={formData.price}
                    onChange={handleInputChange}
                    required 
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Cost Price</label>
                  <input 
                    type="number" 
                    name="cost_price" 
                    step="0.01" 
                    value={formData.cost_price}
                    onChange={handleInputChange}
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Tax Rate (%)</label>
                  <input 
                    type="number" 
                    name="tax_rate" 
                    step="0.01" 
                    min="0" 
                    max="100" 
                    value={formData.tax_rate}
                    onChange={handleInputChange}
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Stock</label>
                  <input 
                    type="number" 
                    name="stock" 
                    value={formData.stock}
                    onChange={handleInputChange}
                    required 
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  />
                </div>
                <div className="flex justify-end">
                  <button 
                    type="button" 
                    onClick={() => setShowAddModal(false)}
                    className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2"
                  >
                    <i className="fas fa-times mr-2"></i>Cancel
                  </button>
                  <button 
                    type="submit" 
                    className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                  >
                    <i className="fas fa-plus mr-2"></i>Add Product
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Edit Product Modal */}
      {showEditModal && editingProduct && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setShowEditModal(false) }}>
          <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <div className="mt-3">
              <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Edit Product</h3>
              <form onSubmit={handleEditProduct}>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                  <input 
                    type="text" 
                    name="name" 
                    value={formData.name}
                    onChange={handleInputChange}
                    required 
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">SKU</label>
                  <input 
                    type="text" 
                    name="sku" 
                    value={formData.sku}
                    onChange={handleInputChange}
                    required 
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Barcode (optional)</label>
                  <input 
                    type="text" 
                    name="barcode" 
                    value={formData.barcode}
                    onChange={handleInputChange}
                    placeholder="Scan or enter barcode"
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Category</label>
                  <select 
                    name="category_id" 
                    value={formData.category_id}
                    onChange={handleInputChange}
                    required 
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  >
                    <option value="">Select Category</option>
                    {categories.map((category) => (
                      <option key={category.id} value={category.id}>{category.name}</option>
                    ))}
                  </select>
                </div>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Price</label>
                  <input 
                    type="number" 
                    name="price" 
                    step="0.01" 
                    value={formData.price}
                    onChange={handleInputChange}
                    required 
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Tax Rate (%)</label>
                  <input 
                    type="number" 
                    name="tax_rate" 
                    step="0.01" 
                    min="0" 
                    max="100" 
                    value={formData.tax_rate}
                    onChange={handleInputChange}
                    className="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-100 bg-white dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  />
                </div>
                <div className="flex justify-end">
                  <button 
                    type="button" 
                    onClick={() => setShowEditModal(false)}
                    className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2"
                  >
                    <i className="fas fa-times mr-2"></i>Cancel
                  </button>
                  <button 
                    type="submit" 
                    className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                  >
                    <i className="fas fa-save mr-2"></i>Update Product
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Products;
