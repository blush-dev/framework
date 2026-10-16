# What you can't do

So that managing people never hands out more than you have, or locks
everyone out, the Users screens refuse some changes:

- You can't give a role, or a capability, that you don't have yourself,
  or change an account that can do something you can't.
- You can't change your own account here. Your password is on
  [**Your Account**](your-account.md); someone else (or `bin/blush`)
  changes the rest.
- Only an [owner](../../concepts/accounts/README.md) can change an owner's account or
  give the Owner role.
- A change that would leave no account able to manage accounts and
  roles (holding all seven of those capabilities) is refused.
- Each action has its own capability, so a role may, say, see accounts
  and suspend them but nothing else. Controls you can't use aren't
  shown.
