export interface EvaluationSingle {
  id: number;
  date: string;
  positive: boolean;
  description: string;
  evaluator: {
    id: number;
    name: string;
    surname: string;
  };
}

export interface UserFindById {
  userFindById: {
    id: number;
    name: string;
    surname: string;
    totalEvaluationScore: number;
    evaluation: EvaluationSingle[];
  };
}

export interface UserFindByIdVariables {
  id: number;
}
