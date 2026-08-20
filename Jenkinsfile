pipeline {
    agent any

    options {
        timestamps()
        disableConcurrentBuilds()
        timeout(time: 30, unit: 'MINUTES')

        // Karena kita checkout manual di stage Checkout.
        skipDefaultCheckout()
    }

    environment {
        COMPOSE_FILE = 'docker-compose.prod.yml'
        COMPOSE_PROJECT_NAME = 'd-form'
    }

    // proses CI/CD e ws masok ya kang jo ganggu su....
    stages {

        // check source code
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Preflight') {
            steps {
                sh '''
                    set -eu

                    echo "=== Docker Version ==="
                    docker --version

                    echo "=== Docker Compose ==="
                    docker compose version

                    echo "=== Docker Daemon ==="
                    docker info >/dev/null

                    echo "=== doscom_ingress Network ==="
                    docker network inspect doscom_ingress >/dev/null

                    echo "Preflight OK"
                '''
            }
        }

        // cek env variable e ws masuk durung
        stage('Validation') {
            steps {
                withCredentials([
                    file(
                        credentialsId: 'd-form-production-env',
                        variable: 'APP_ENV_FILE'
                    ),
                    string(
                        credentialsId: 'd-form-db-root-password',
                        variable: 'DB_ROOT_PASSWORD'
                    )
                ]) {
                    sh '''
                        set -eu

                        echo "=== Validating Docker Compose ==="

                        docker compose \
                            --env-file "$APP_ENV_FILE" \
                            -f "$COMPOSE_FILE" \
                            config -q

                        echo "Docker Compose configuration valid"
                    '''
                }
            }
        }

        stage('Build') {
            when {
                branch 'main'
            }

            steps {
                withCredentials([
                    file(
                        credentialsId: 'd-form-production-env',
                        variable: 'APP_ENV_FILE'
                    ),
                    string(
                        credentialsId: 'd-form-db-root-password',
                        variable: 'DB_ROOT_PASSWORD'
                    )
                ]) {
                    sh '''
                        set -eu

                        echo "=== Building D-Form ==="

                        docker compose \
                            --env-file "$APP_ENV_FILE" \
                            -f "$COMPOSE_FILE" \
                            build app
                    '''
                }
            }
        }

        stage('Deploy') {
            when {
                branch 'main'
            }

            steps {
                withCredentials([
                    file(
                        credentialsId: 'd-form-production-env',
                        variable: 'APP_ENV_FILE'
                    ),
                    string(
                        credentialsId: 'd-form-db-root-password',
                        variable: 'DB_ROOT_PASSWORD'
                    )
                ]) {
                    sh '''
                        set -eu

                        echo "=== Deploying D-Form ==="

                        docker compose \
                            --env-file "$APP_ENV_FILE" \
                            -f "$COMPOSE_FILE" \
                            up -d \
                            --remove-orphans \
                            --wait \
                            --wait-timeout 180
                    '''
                }
            }
        }

        stage('Verify') {
            when {
                branch 'main'
            }

            steps {
                sh '''
                    set -eu

                    echo "=== D-Form Containers ==="

                    docker ps \
                        --filter "name=d-form-" \
                        --format "table {{.Names}}\\t{{.Status}}\\t{{.Networks}}"

                    echo "=== Checking Application Health ==="

                    test "$(docker inspect \
                        -f '{{.State.Health.Status}}' \
                        prod_d-form-app-core)" = "healthy"

                    echo "D-Form is healthy"
                '''
            }
        }
    }

    // ini di jalanke nk semua stage selesai 
    post {

        // nk pipeline e berhasil tanpa kendala 
        success {
            echo 'D-Form deployment successful. PUKIMAK KAU....'
        }

        // nk stage e ono sg gagal tampilke logs dll
        failure {
            echo 'D-Form deployment failed.'

            sh '''
                echo "=== Containers ==="
                docker ps -a --filter "name=d-form-" || true

                echo "=== App Logs ==="
                docker logs --tail 150 d-form-app 2>&1 || true

                echo "=== DB Logs ==="
                docker logs --tail 100 d-form-db 2>&1 || true

                echo "=== Redis Logs ==="
                docker logs --tail 100 d-form-redis 2>&1 || true
            '''
        }
    }
}