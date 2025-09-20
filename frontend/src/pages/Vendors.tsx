import React, { useState, useEffect } from 'react';
import { useAuth } from '../hooks/useAuth';
import api from '../services/api';
import { useConfirm } from '../contexts/ConfirmContext';

interface Vendor {
  id: number;
  name: string;
  contact_person: string;
  phone: string;
  email: string;
  address: string;
}

const Vendors: React.FC = () => {
  const { } = useAuth();
  const { confirm } = useConfirm();
  const [vendors, setVendors] = useState<Vendor[]>([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [editingVendor, setEditingVendor] = useState<Vendor | null>(null);
  const [formData, setFormData] = useState({
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: ''
  });

  useEffect(() => {
    fetchVendors();
  }, []);

  const fetchVendors = async () => {
    try {
      setLoading(true);
      const response = await api.get('vendors_management.php');
      if (response.data.success) {
        setVendors(response.data.vendors);
      }
    } catch (error) {
      console.error('Error fetching vendors:', error);
      setError('Error loading vendors');
    } finally {
      setLoading(false);
    }
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const resetForm = () => {
    setFormData({
      name: '',
      contact_person: '',
      phone: '',
      email: '',
      address: ''
    });
  };

  const handleAddVendor = async (e: React.FormEvent) => {
    e.preventDefault();
    
    try {
      const response = await api.post('vendors_management.php', {
        action: 'add',
        ...formData
      });

      if (response.data.success) {
        setMessage('Vendor added successfully.');
        setShowAddModal(false);
        resetForm();
        fetchVendors();
      } else {
        setError(response.data.message || 'Error adding vendor');
      }
    } catch (error) {
      console.error('Error adding vendor:', error);
      setError('Error adding vendor');
    }
  };

  const handleEditVendor = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!editingVendor) return;

    try {
      const response = await api.post('vendors_management.php', {
        action: 'edit',
        id: editingVendor.id,
        ...formData
      });

      if (response.data.success) {
        setMessage('Vendor updated successfully.');
        setShowEditModal(false);
        setEditingVendor(null);
        resetForm();
        fetchVendors();
      } else {
        setError(response.data.message || 'Error updating vendor');
      }
    } catch (error) {
      console.error('Error updating vendor:', error);
      setError('Error updating vendor');
    }
  };

  const handleDeleteVendor = async (id: number) => {
    const ok = await confirm({
      title: 'Delete vendor?',
      message: 'This action cannot be undone.',
      confirmText: 'Delete',
      danger: true,
    });
    if (!ok) return;

    try {
      const response = await api.post('vendors_management.php', {
        action: 'delete',
        id: id
      });

      if (response.data.success) {
        setMessage('Vendor deleted successfully.');
        fetchVendors();
      } else {
        setError(response.data.message || 'Error deleting vendor');
      }
    } catch (error) {
      console.error('Error deleting vendor:', error);
      setError('Error deleting vendor');
    }
  };

  const openEditModal = (vendor: Vendor) => {
    setEditingVendor(vendor);
    setFormData({
      name: vendor.name,
      contact_person: vendor.contact_person,
      phone: vendor.phone,
      email: vendor.email,
      address: vendor.address
    });
    setShowEditModal(true);
  };

  const openAddModal = () => {
    resetForm();
    setShowAddModal(true);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600 dark:text-gray-400">Loading vendors...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
      <div className="container mx-auto px-4 py-8">
        <div className="flex justify-between items-center mb-8">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
            <i className="fas fa-truck mr-3"></i>Vendor Management
          </h1>
          <button 
            onClick={openAddModal}
            className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
          >
            <i className="fas fa-plus mr-2"></i>Add New Vendor
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

        {/* Vendors Table */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md">
          <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Vendors</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Contact Person</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Phone</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Address</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {vendors.length > 0 ? (
                  vendors.map((vendor) => (
                    <tr key={vendor.id}>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                        {vendor.name}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        {vendor.contact_person}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        {vendor.phone}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {vendor.email}
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                        {vendor.address}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button 
                          onClick={() => openEditModal(vendor)}
                          className="text-indigo-600 hover:text-indigo-900 mr-3"
                        >
                          <i className="fas fa-edit mr-1"></i>Edit
                        </button>
                        <button 
                          onClick={() => handleDeleteVendor(vendor.id)}
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
                      No vendors found
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Add Vendor Modal */}
      {showAddModal && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setShowAddModal(false) }}>
          <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <div className="mt-3">
              <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Add New Vendor</h3>
              <form onSubmit={handleAddVendor}>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vendor Name *</label>
                  <input 
                    type="text" 
                    name="name" 
                    value={formData.name}
                    onChange={handleInputChange}
                    required
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Contact Person *</label>
                  <input 
                    type="text" 
                    name="contact_person" 
                    value={formData.contact_person}
                    onChange={handleInputChange}
                    required
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone *</label>
                  <input 
                    type="text" 
                    name="phone" 
                    value={formData.phone}
                    onChange={handleInputChange}
                    required
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                  <input 
                    type="email" 
                    name="email" 
                    value={formData.email}
                    onChange={handleInputChange}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Address</label>
                  <textarea 
                    name="address" 
                    rows={3}
                    value={formData.address}
                    onChange={handleInputChange}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                
                <div className="flex justify-end space-x-4">
                  <button 
                    type="button" 
                    onClick={() => setShowAddModal(false)}
                    className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                  >
                    <i className="fas fa-times mr-2"></i>Cancel
                  </button>
                  <button 
                    type="submit" 
                    className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                  >
                    <i className="fas fa-plus mr-2"></i>Add Vendor
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Edit Vendor Modal */}
      {showEditModal && editingVendor && (
        <div className="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full modal-overlay" onClick={(e) => { if (e.target === e.currentTarget) setShowEditModal(false) }}>
          <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onClick={(e) => e.stopPropagation()}>
            <div className="mt-3">
              <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Edit Vendor</h3>
              <form onSubmit={handleEditVendor}>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vendor Name *</label>
                  <input 
                    type="text" 
                    name="name" 
                    value={formData.name}
                    onChange={handleInputChange}
                    required
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Contact Person *</label>
                  <input 
                    type="text" 
                    name="contact_person" 
                    value={formData.contact_person}
                    onChange={handleInputChange}
                    required
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone *</label>
                  <input 
                    type="text" 
                    name="phone" 
                    value={formData.phone}
                    onChange={handleInputChange}
                    required
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                  <input 
                    type="email" 
                    name="email" 
                    value={formData.email}
                    onChange={handleInputChange}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Address</label>
                  <textarea 
                    name="address" 
                    rows={3}
                    value={formData.address}
                    onChange={handleInputChange}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                  />
                </div>
                
                <div className="flex justify-end space-x-4">
                  <button 
                    type="button" 
                    onClick={() => setShowEditModal(false)}
                    className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                  >
                    <i className="fas fa-times mr-2"></i>Cancel
                  </button>
                  <button 
                    type="submit" 
                    className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                  >
                    <i className="fas fa-save mr-2"></i>Update Vendor
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

export default Vendors;
