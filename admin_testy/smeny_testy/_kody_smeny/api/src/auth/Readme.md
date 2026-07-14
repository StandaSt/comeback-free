# Auth module
## auth.service
- Provides basic auth methods fot authorization.
- To use, inject it in constructor.

## currentUser.decorator
- Provides currently logged user.
```js
async userGetLogged(@CurrentUser() userId: number) {
    return this.userService.findById(userId);
}
```

## field.guard
- Provides acl functionality over graphql field.
- Endpoint must be decorated with `@UseInterceptors(ResourceInterceptor)` or any decorator that implements it.
- As parameters accepts resources that user need to access the field. 
If they are not met the field is returned to client with `null` value.
```js
@ObjectType()
class User {
  @Field()
  readonly id: number;

  @Field({ nullable: true })
  @FieldGuard('test')
  email: string;
}

export default User;
``` 
 
## jwt.guard
- Checks if user has valid token. If not return 401 exception.
```js
@UseGuards(GqlAuthGuard)
async userGetLogged(@CurrentUser() userId: number) {
    return this.userService.findById(userId);
}
```

## jwt.strategy
- jwt settings.

## resource.decorator
- Provides resources to `resource.guard`.
```js
@ResourceDecorator('resource1', 'resource2')
async userGetLogged(@CurrentUser() userId: number) {
  return this.userService.findById(userId);
}
```

## resource.guard.ts
- Check if user has sufficient resources. If not it throws 401 exception.
- `resource.decorator` must be placed before it.
```js
@UseGuards(ResourceGuard)
async userGetLogged(@CurrentUser() userId: number) {
  return this.userService.findById(userId);
}
```

## resource.interceptor
- Translate `field.guard` and other fields with `@Translate()`.
```js
@UseInterceptors(ResourceInterceptor)
async userGetLogged(@CurrentUser() userId: number) {
  return this.userService.findById(userId);
}
```

## secured.guard
- Combines `resoucre.decorator`, `resource.guard`, `jwt.guard` and `resource.interceptor`
```js
@Secured()
async userGetLogged(@CurrentUser() userId: number) {
  return this.userService.findById(userId);
}
```