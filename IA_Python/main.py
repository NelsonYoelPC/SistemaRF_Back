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

class ConnectionManager:
    def __init__(self):
        self.active_connections: list[WebSocket] = []

    async def connect(self, websocket: WebSocket):
        await websocket.accept()
        self.active_connections.append(websocket)

    def disconnect(self, websocket: WebSocket):
        if websocket in self.active_connections:
            self.active_connections.remove(websocket)

    async def broadcast_image(self, message: any, sender: WebSocket, is_binary: bool = False):
        for connection in self.active_connections:
            if connection != sender:
                try:
                    if is_binary:
                        await connection.send_bytes(message)
                    else:
                        await connection.send_text(message)
                except:
                    pass

manager = ConnectionManager()

import time

@app.websocket("/ws/recognition")
async def websocket_recognition(websocket: WebSocket):
    await manager.connect(websocket)
    print("Nuevo cliente conectado al sistema de retransmisión")
    
    last_recognition_time = 0
    
    try:
        while True:
            # Recibir datos (pueden ser bytes o texto)
            message = await websocket.receive()
            
            data = None
            is_binary = False
            
            if "bytes" in message:
                data = message["bytes"]
                is_binary = True
            elif "text" in message:
                data = message["text"]
                is_binary = False
            
            if data:
                # 1. Retransmitir INMEDIATAMENTE
                await manager.broadcast_image(data, websocket, is_binary)
                
                # 2. Reconocimiento Facial (Solo si son bytes/imagen)
                current_time = time.time()
                if is_binary and (current_time - last_recognition_time > 2.0):
                    try:
                        # Procesar en segundo plano
                        result = service.find_face(data)
                        last_recognition_time = current_time
                        
                        if result:
                            await websocket.send_json({
                                "status": "success",
                                "recognized": True,
                                "data": result
                            })
                    except Exception as e:
                        print(f"Error IA: {e}")
                
    except WebSocketDisconnect:
        manager.disconnect(websocket)
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
