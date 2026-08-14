search .env.production :

```sudo find /home /opt /srv -type f \
  \( -name ".env" -o -name ".env.production" \) \
  2>/dev/null | grep -i d-form
```
sudo cat /home/doscomguest/d-form-v2/.env.production


cari volume d-form-v2 yg dulu :
```
sudo docker volume ls | grep d-form
```