import { createParamDecorator } from '@nestjs/common';

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const CurrentUser = createParamDecorator(
  (data, [root, args, ctx, info]) => ctx.req.user,
);

export default CurrentUser;
