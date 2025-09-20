import React, { useState, useEffect } from 'react';
import { useAuth } from '../hooks/useAuth';
import api from '../services/api';
import { StatsCardSkeleton } from '../components/Skeleton';

interface DashboardStats {
  products: number;
  sales_today: number;
  amount_today: number;
  low_stock: number;
}

interface RecentSale {
  id: number;
  date: string;
  total_amount: number;
  cashier_name: string;
}

const Dashboard: React.FC = () => {
  const { isAuthenticated, loading: authLoading } = useAuth();
  const [stats, setStats] = useState<DashboardStats>({
    products: 0,
    sales_today: 0,
    amount_today: 0,
    low_stock: 0
  });
  const [recentSales, setRecentSales] = useState<RecentSale[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Only fetch data if user is authenticated and auth check is complete
    if (!authLoading && isAuthenticated) {
      const fetchDashboardData = async () => {
        try {
          setLoading(true);
          
          // Fetch dashboard statistics
          const statsResponse = await api.get('dashboard_stats.php');
          if (statsResponse.data.success) {
            setStats(statsResponse.data.stats);
          }

          // Fetch recent sales
          const salesResponse = await api.get('recent_sales.php');
          if (salesResponse.data.success) {
            setRecentSales(salesResponse.data.sales);
          }
        } catch (error) {
          console.error('Error fetching dashboard data:', error);
        } finally {
          setLoading(false);
        }
      };

      fetchDashboardData();
    } else if (!authLoading && !isAuthenticated) {
      // If not authenticated, stop loading
      setLoading(false);
    }
  }, [authLoading, isAuthenticated]);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        <div className="container mx-auto px-4 py-8">
          <div className="mb-8">
            <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
              <i className="fas fa-tachometer-alt mr-3"></i>Dashboard
            </h1>
          </div>

          {/* Statistics Cards Skeleton */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <StatsCardSkeleton />
            <StatsCardSkeleton />
            <StatsCardSkeleton />
            <StatsCardSkeleton />
          </div>

          {/* Recent Sales Skeleton */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md">
            <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Sales</h2>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead className="bg-gray-50 dark:bg-gray-900">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cashier</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Amount</th>
                  </tr>
                </thead>
                <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                  {[...Array(5)].map((_, index) => (
                    <tr key={index} className="animate-pulse">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="h-4 bg-gray-100 dark:bg-gray-700 rounded w-24"></div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="h-4 bg-gray-100 dark:bg-gray-700 rounded w-20"></div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="h-4 bg-gray-100 dark:bg-gray-700 rounded w-16"></div>
                      </td>
                    </tr>
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
            <i className="fas fa-tachometer-alt mr-3"></i>Dashboard
          </h1>
        </div>

        {/* Statistics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {/* Total Products */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-blue-500">
            <div className="flex items-center">
              <div className="flex-1">
                <p className="text-sm font-medium text-blue-600 uppercase tracking-wide">Total Products</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{stats.products}</p>
              </div>
              <div className="text-blue-500">
                <i className="fas fa-box text-3xl"></i>
              </div>
            </div>
          </div>

          {/* Sales Today */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <div className="flex items-center">
              <div className="flex-1">
                <p className="text-sm font-medium text-green-600 uppercase tracking-wide">Sales Today</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{stats.sales_today}</p>
              </div>
              <div className="text-green-500">
                <i className="fas fa-shopping-cart text-3xl"></i>
              </div>
            </div>
          </div>

          {/* Revenue Today */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-indigo-500">
            <div className="flex items-center">
              <div className="flex-1">
                <p className="text-sm font-medium text-indigo-600 uppercase tracking-wide">Revenue Today</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">Rs. {stats.amount_today.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
              </div>
              <div className="text-indigo-500">
                <i className="fas fa-money-bill-wave text-3xl"></i>
              </div>
            </div>
          </div>

          {/* Low Stock Items */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
            <div className="flex items-center">
              <div className="flex-1">
                <p className="text-sm font-medium text-yellow-600 uppercase tracking-wide">Low Stock Items</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{stats.low_stock}</p>
              </div>
              <div className="text-yellow-500">
                <i className="fas fa-exclamation-triangle text-3xl"></i>
              </div>
            </div>
          </div>
        </div>

        {/* Recent Sales */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md">
          <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Sales</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cashier</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Amount</th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {recentSales.length > 0 ? (
                  recentSales.map((sale) => (
                    <tr key={sale.id}>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        {new Date(sale.date).toLocaleDateString('en-US', { 
                          month: 'short', 
                          day: 'numeric', 
                          year: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit'
                        })}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        {sale.cashier_name}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                        Rs. {sale.total_amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={3} className="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                      No recent sales found
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
