import { CombinedState, combineReducers, createStore, Store } from 'redux';

import roles from './roles';
import { State } from './types';

const appReducer = combineReducers({
  roles,
});

const rootReducer = (state, action): CombinedState<State> =>
  appReducer(state, action);

const makeStore = (): Store => {
  let store;

  if (process.browser) {
    store = createStore(rootReducer);
  } else {
    store = createStore(rootReducer);
  }

  return store;
};

const store = makeStore();

export default store;
