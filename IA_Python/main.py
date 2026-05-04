import os
import base64
from fastapi import FastAPI, UploadFile, File, WebSocket, WebSocketDisconnect
from fastapi.middleware.cors import CORSMiddleware
import uvicorn
from core.recognition import RecognitionService

app = FastAPI(title="Sistema de Reconocimiento Facial Pro")

# Configurar CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Inicializar servicio de reconocimiento
# Usamos la ruta relativa a base_datos
service = RecognitionService(db_path="./base_datos")

@app.get("/")
async def root():
    return {"message": "Servidor de IA (FastAPI) activo", "status": "online"}

@app.post("/search_face")
async def search_face(file: UploadFile = File(...)):
    """
    Endpoint clásico (POST) para compatibilidad
    """
    content = await file.read()
    result = service.find_face(content)
    
    if result:
        return {
            "message": "Rostro reconocido.",
            "match_folder": result["match_folder"],
            "match_file": result["match_file"]
        }
    
    return {"message": "No se encontraron coincidencias."}, 404

@app.websocket("/ws/recognition")
async def websocket_recognition(websocket: WebSocket):
    """
    WebSocket para reconocimiento masivo y automático
    """
    await websocket.accept()
    print("Cliente conectado por WebSocket")
    
    try:
        while True:
            # Recibir imagen en base64 desde el cliente
            data = await websocket.receive_text()
            
            try:
                # Limpiar prefijo base64 si existe
                if "," in data:
                    data = data.split(",")[1]
                
                img_bytes = base64.b64decode(data)
                
                # Procesar reconocimiento
                result = service.find_face(img_bytes)
                
                if result:
                    await websocket.send_json({
                        "status": "success",
                        "recognized": True,
                        "data": result
                    })
                else:
                    await websocket.send_json({
                        "status": "success",
                        "recognized": false,
                        "message": "Buscando..."
                    })
                    
            except Exception as e:
                await websocket.send_json({"status": "error", "message": str(e)})
                
    except WebSocketDisconnect:
        print("Cliente desconectado")

if __name__ == "__main__":
    # Configuración de certificados SSL
    ssl_cert = "./certs/backend.crt"
    ssl_key = "./certs/backend.key"
    
    # Verificar si existen los certificados
    if os.path.exists(ssl_cert) and os.path.exists(ssl_key):
        print("Iniciando con SSL habilitado...")
        uvicorn.run(
            app, 
            host="0.0.0.0", 
            port=5050, 
            ssl_keyfile=ssl_key, 
            ssl_certfile=ssl_cert
        )
    else:
        print("Certificados no encontrados, iniciando en modo HTTP normal...")
        uvicorn.run(app, host="0.0.0.0", port=5050)
