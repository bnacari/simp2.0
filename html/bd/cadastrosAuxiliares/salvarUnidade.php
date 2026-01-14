<?php
// bd/cadastrosAuxiliares/salvarUnidade.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $codigo = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    
    // Validações
    if (empty($codigo)) {
        echo json_encode(['success' => false, 'message' => 'O código é obrigatório']);
        exit;
    }
    
    if (empty($nome)) {
        echo json_encode(['success' => false, 'message' => 'O nome é obrigatório']);
        exit;
    }
    
    // Verificar duplicidade de código
    $sqlCheck = "SELECT CD_UNIDADE FROM SIMP.dbo.UNIDADE WHERE CD_CODIGO = :codigo";
    if ($id) {
        $sqlCheck .= " AND CD_UNIDADE != :id";
    }
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->bindValue(':codigo', $codigo);
    if ($id) {
        $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
    }
    $stmtCheck->execute();
    
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe uma unidade com este código']);
        exit;
    }
    
    if ($id) {
        // UPDATE
        $sql = "UPDATE SIMP.dbo.UNIDADE SET 
                    CD_CODIGO = :codigo,
                    DS_NOME = :nome
                WHERE CD_UNIDADE = :id";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':codigo' => $codigo,
            ':nome' => $nome,
            ':id' => $id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Unidade atualizada com sucesso!']);
    } else {
        // INSERT
        $sql = "INSERT INTO SIMP.dbo.UNIDADE (CD_CODIGO, DS_NOME) VALUES (:codigo, :nome)";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':codigo' => $codigo,
            ':nome' => $nome
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Unidade cadastrada com sucesso!']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar unidade: ' . $e->getMessage()
    ]);
}