import React from 'react';
import { useMutation } from '@apollo/react-hooks';
import { useSnackbar } from 'notistack';

import USER_ADD_EVALUATION from '../mutations/addEvaluation';
import apiErrors from '../../../../../../shared/config/api/errors';

import AddEvaluation from './addEvaluation';
import {
  AddEvaluationIndexProps,
  AddEvaluationVariables,
  AddEvaluation as AddEvaluationType,
} from './types';

const AddEvaluationIndex: React.FC<AddEvaluationIndexProps> = props => {
  const [addEvaluation, { loading }] = useMutation<
    AddEvaluationType,
    AddEvaluationVariables
  >(USER_ADD_EVALUATION, {
    refetchQueries: ['UserFindById'],
    awaitRefetchQueries: true,
  });
  const { enqueueSnackbar } = useSnackbar();

  const submitHandler = (
    positive: boolean,
    description: string,
  ): Promise<void> => {
    return addEvaluation({
      variables: {
        userId: props.userId,
        positive,
        description,
      },
    })
      .then(() => {
        enqueueSnackbar('Hodnocení úspěšně přidáno', { variant: 'success' });
      })
      .catch(error => {
        if (
          error.graphQLErrors.some(
            e => e.message.message === apiErrors.evaluation.cooldown,
          )
        ) {
          enqueueSnackbar(
            'Nemůžete přidat hodnocení tomuto uživateli z důvodu limitování frekvence přidávání. Zkuste to za pár hodin',
            { variant: 'warning' },
          );
        } else {
          enqueueSnackbar('Nepovedlo se přidat hodnocení', {
            variant: 'error',
          });
        }
      });
  };

  return <AddEvaluation onSubmit={submitHandler} loading={loading} />;
};

export default AddEvaluationIndex;
