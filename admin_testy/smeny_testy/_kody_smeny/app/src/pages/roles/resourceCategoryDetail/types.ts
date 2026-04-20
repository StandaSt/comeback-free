interface Category {
  id: number;
  name: string;
  resources: {
    id: number;
    name: string;
  }[];
}

export interface ResourceCategoryFindById {
  resourceCategoryFindById: Category;
}

export interface ResourceCategoryFindByIdVars {
  id: number;
}

export interface ResourceCategoryDetailProps {
  category: Category;
}
