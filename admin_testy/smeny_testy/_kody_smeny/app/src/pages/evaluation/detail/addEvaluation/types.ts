export interface AddEvaluationProps {
  onSubmit: (positive: boolean, description: string) => Promise<void>;
  loading: boolean;
}

export interface AddEvaluationIndexProps {
  userId: number;
}

export interface AddEvaluation {
  userAddEvaluation: {
    id: number;
    evaluation: {
      id: number;
      description: string;
      date: string;
    }[];
  };
}

export interface AddEvaluationVariables {
  userId: number;
  description: string;
  positive: boolean;
}
