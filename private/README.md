### To make the self signed ssl certificate use the commands below:

In this folder

```languadge-bash
brew install mkcert
mkcert -install -key-file key.pem -cert-file cert.pem ohcrud.loc "*.ohcrud.loc" localhost 127.0.0.1 ::1
```

Run hurl tests
```language-bash

```