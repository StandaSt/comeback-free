export interface Evaluation {
  id: number;
  positive: boolean;
  description: string;
}

export interface EvaluationQuery {
  userGetLogged: {
    evaluation: Evaluation[];
  };
}

export interface MyEvaluationProps {
  evaluation: Evaluation[];
  loading: boolean;
}
