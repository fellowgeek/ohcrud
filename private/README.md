### Generate a Self-Signed SSL Certificate

1. Install **mkcert** and generate the certificate files in this directory:

```language-bash
brew install mkcert
mkcert -install -key-file key.pem -cert-file cert.pem ohcrud.local "*.ohcrud.local" localhost 127.0.0.1 ::1
```

2. Update your hosts file to point `ohcrud.local` to `127.0.0.1`:

```language-bash
sudo sh -c 'echo "127.0.0.1 ohcrud.local" >> /etc/hosts'
```

### Run the Hurl Tests

Make the test script executable and run it:

```language-bash
chmod +x test.sh
./test.sh
```
