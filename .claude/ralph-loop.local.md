---
active: true
iteration: 1
max_iterations: 0
completion_promise: null
started_at: "2026-02-26T18:52:09Z"
---

na tela de modelosML.php existe a opção de treinar a machine learning, para não sobrecarregar o servidor com demandas paralelas de usuários, vamos criar uma fila, sendo que um treinamento só começa a rodar se não houver nenhum em execução. se houver algum na fila, mostre para o usuário que ele é o número X na fila e começará em breve, assim que começar, vá informando o andamento assim como já é feito hoje.
