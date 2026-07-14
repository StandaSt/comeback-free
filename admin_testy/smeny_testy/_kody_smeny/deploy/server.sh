echo "=== Deploy starts ==="

YARN="~/.nvm/versions/node/v14.15.0/bin/node ~/.nvm/versions/node/v14.15.0/lib/node_modules/yarn/bin/yarn.js"
runWithYarn() {
  eval "$YARN $1"
}
cd "/var/www/shift-planner-$ENVIROMENT" || exit
cd app || exit
runWithYarn "prod:stop"
cd ../api || exit
runWithYarn prod:stop
echo "=== App stopped ==="
cd ../.. || exit

rm -rf "shift-planner-$ENVIROMENT"
mkdir "shift-planner-$ENVIROMENT"

cp shift-planner-deploy/502.html "shift-planner-$ENVIROMENT/502.html"

tar xf /var/www/shift-planner-deploy/bundled.tar.gz -C "/var/www/shift-planner-$ENVIROMENT/"

echo "=== App extracted ==="
cd "/var/www/shift-planner-$ENVIROMENT/api" || exit
runWithYarn migration:run:simple
runWithYarn prod:start
cd ../app || exit
if [ "$ENVIROMENT" = "development" ]; then
  PORT=3001 runWithYarn prod:start || exit
else
  runWithYarn prod:start
fi
echo "=== App started ==="

echo "=== Deploy ends ==="
