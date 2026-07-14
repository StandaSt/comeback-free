echo "=== Build start ==="
cd ..

mkdir shared/old-config
mv shared/config/app/index.ts shared/old-config/app.ts
mv shared/config/api/index.ts shared/old-config/api.ts
mv api/ormconfig.json api/ormconfig-old.json

if [ "$ENVIROMENT" = "development" ]; then
  cp deploy/config/development/app.ts shared/config/app/index.ts
  cp deploy/config/development/api.ts shared/config/api/index.ts
  cp deploy/config/development/ormconfig.json api/ormconfig.json
elif [ "$ENVIROMENT" = "production" ]; then
  cp deploy/config/production/app.ts shared/config/app/index.ts
  cp deploy/config/production/api.ts shared/config/api/index.ts
  cp deploy/config/production/ormconfig.json api/ormconfig.json
fi

rm -rf ../shift-planner-deploy/bundled.tar.gz
cd api || exit
yarn conf || exit
yarn build || exit
cd ../app || exit
yarn build || exit
cd ..
tar -czf ../shift-planner-deploy/bundled.tar.gz ./

echo "=== Build end ==="
echo "=== Transfer start ==="
scp -r ../shift-planner-deploy/bundled.tar.gz root@68.183.71.27:/var/www/shift-planner-deploy/bundled.tar.gz || exit
scp ./deploy/server.sh root@68.183.71.27:/var/www/shift-planner-deploy/server.sh || exit
scp ./deploy/502.html root@68.183.71.27:/var/www/shift-planner-deploy/502.html || exit
echo "=== Transfer end ==="
ssh root@68.183.71.27 "ENVIROMENT=$ENVIROMENT bash /var/www/shift-planner-deploy/server.sh"
