import React from 'react';

interface SkeletonProps {
  className?: string;
  width?: string;
  height?: string;
}

export const Skeleton: React.FC<SkeletonProps> = ({ 
  className = '', 
  width = 'w-full', 
  height = 'h-4' 
}) => {
  return (
    <div 
      className={`bg-gray-200 dark:bg-gray-700 rounded animate-pulse ${width} ${height} ${className}`}
    />
  );
};

export const ProductCardSkeleton: React.FC = () => (
  <div className="p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
    <div className="space-y-2">
      <Skeleton height="h-4" />
      <Skeleton height="h-3" width="w-3/4" />
      <Skeleton height="h-3" width="w-1/2" />
      <div className="flex items-center justify-between mt-2">
        <Skeleton height="h-4" width="w-16" />
        <Skeleton height="h-3" width="w-8" />
      </div>
      <Skeleton height="h-2" width="w-12" />
    </div>
  </div>
);

export const CartItemSkeleton: React.FC = () => (
  <div className="border border-gray-200 dark:border-gray-700 rounded p-3">
    <div className="flex items-center justify-between">
      <div className="flex items-center space-x-3 flex-1">
        <Skeleton height="h-4" width="w-4" />
        <div className="space-y-1 flex-1">
          <Skeleton height="h-4" width="w-3/4" />
          <Skeleton height="h-3" width="w-1/2" />
        </div>
      </div>
      <div className="flex items-center space-x-2">
        <Skeleton height="h-8" width="w-8" />
        <Skeleton height="h-6" width="w-12" />
        <Skeleton height="h-8" width="w-8" />
      </div>
    </div>
  </div>
);

export const TableRowSkeleton: React.FC = () => (
  <tr className="animate-pulse">
    <td className="px-4 py-3">
      <Skeleton height="h-4" width="w-8" />
    </td>
    <td className="px-4 py-3">
      <Skeleton height="h-4" width="w-32" />
    </td>
    <td className="px-4 py-3">
      <Skeleton height="h-4" width="w-24" />
    </td>
    <td className="px-4 py-3">
      <Skeleton height="h-4" width="w-16" />
    </td>
    <td className="px-4 py-3">
      <Skeleton height="h-4" width="w-20" />
    </td>
  </tr>
);

export const StatsCardSkeleton: React.FC = () => (
  <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-gray-300">
    <div className="flex items-center">
      <div className="flex-1">
        <Skeleton height="h-3" width="w-24" className="mb-2" />
        <Skeleton height="h-8" width="w-16" />
      </div>
      <div className="text-gray-300">
        <Skeleton height="h-12" width="w-12" className="rounded" />
      </div>
    </div>
  </div>
);

export const ButtonSkeleton: React.FC = () => (
  <Skeleton height="h-10" width="w-full" className="rounded" />
);

export const InputSkeleton: React.FC = () => (
  <Skeleton height="h-10" width="w-full" className="rounded-md" />
);

export const ModalSkeleton: React.FC = () => (
  <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div className="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md mx-4">
      <div className="space-y-4">
        <Skeleton height="h-6" width="w-32" />
        <InputSkeleton />
        <InputSkeleton />
        <div className="flex space-x-2">
          <ButtonSkeleton />
          <ButtonSkeleton />
        </div>
      </div>
    </div>
  </div>
);
