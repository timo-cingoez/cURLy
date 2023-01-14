# cURLy
A simple PHP (7.4) cURL wrapper.

### Example
```
$cURLy = new cURLy('https://jsonplaceholder.typicode.com/todos/1', [CURLOPT_CERTINFO => true, CURLOPT_CRLF >= true]);
$cURLy->setLog(true);
$cURLy->setResponseType('OBJECT');
$cURLy->GET();
```

### Method chaining is also supported
```
$getResponse = cURLy::instance('https://jsonplaceholder.typicode.com/todos/1')->GET();
```

```
$data = ['title' => 'foo', 'body' => 'bar', 'userId' => 1];
$postResponse = cURLy::instance('https://jsonplaceholder.typicode.com/posts')->setLog(true)->setFormat('JSON')->POST($data);
```

