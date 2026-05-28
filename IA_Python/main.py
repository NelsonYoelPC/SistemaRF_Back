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
        self.locked_cameras: dict[int, WebSocket] = {}

    async def connect(self, websocket: WebSocket):
        await websocket.accept()
        self.active_connections.append(websocket)

    def disconnect(self, websocket: WebSocket):
        if websocket in self.active_connections:
            self.active_connections.remove(websocket)
        # Liberar cámaras ocupadas por este cliente
        freed_cameras = [cam_id for cam_id, ws in self.locked_cameras.items() if ws == websocket]
        for cam_id in freed_cameras:
            del self.locked_cameras[cam_id]
        return freed_cameras

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
import json
import threading
import requests
import base64

import cv2
import numpy as np
from tensorflow.keras.applications.mobilenet_v2 import MobileNetV2, preprocess_input
from scipy.spatial.distance import cosine

# --- Inicialización Re-ID ---
print("Cargando modelo Re-ID (MobileNetV2)...")
import os
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '2' # Suprimir warnings de TF
reid_model = MobileNetV2(weights='imagenet', include_top=False, pooling='avg')
print("Modelo Re-ID cargado correctamente.")

last_seen_time_per_person = {}
person_reid_features = {}
REID_THRESHOLD = 0.20 # Distancia < 0.20 significa > 80% de similitud de ropa

def enviar_alerta_laravel(persona_id, camara_id, image_bytes):
    global last_seen_time_per_person
    global person_reid_features
    global last_seen_time_per_person
    current_time = time.time()
    
    # LÓGICA DE SESIÓN:
    # Si vimos a la persona hace menos de 15 segundos, asumimos que sigue parada frente a la cámara.
    # Actualizamos su "última vez visto" pero NO guardamos un nuevo registro en la Base de Datos.
    if persona_id in last_seen_time_per_person and (current_time - last_seen_time_per_person[persona_id] < 15.0):
        # Sigue en cámara. Actualizamos tiempo y evitamos registrar BD.
        last_seen_time_per_person[persona_id] = current_time
        return

    # LÓGICA DE RE-ID (COMPROBAR ROPA)
    try:
        nparr = np.frombuffer(image_bytes, np.uint8)
        img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        img_resized = cv2.resize(img, (224, 224))
        img_batch = np.expand_dims(img_resized, axis=0)
        img_preprocessed = preprocess_input(img_batch)
        
        current_features = reid_model.predict(img_preprocessed, verbose=0)[0]
    except Exception as e:
        print(f"Error procesando Re-ID: {e}")
        return

    # Comparamos si ya lo habíamos visto hoy/en la sesión
    if persona_id in person_reid_features:
        dist = cosine(current_features, person_reid_features[persona_id])
        similitud = (1 - dist) * 100
        
        if dist < REID_THRESHOLD:
            print(f"Objetivo {persona_id} reapareció con la MISMA ropa (similitud: {similitud:.1f}%). Omitiendo alerta BD.")
            last_seen_time_per_person[persona_id] = current_time
            return
        else:
            print(f"Objetivo {persona_id} CAMBIÓ de ropa (similitud: {similitud:.1f}%). Registrando NUEVA alerta.")

    # Guardamos los nuevos datos de sesión
    last_seen_time_per_person[persona_id] = current_time
    person_reid_features[persona_id] = current_features

    try:
        url = "http://192.168.1.38:8000/api/alertas"
        base64_image = base64.b64encode(image_bytes).decode('utf-8')
        payload = {
            "usuario_id": persona_id,
            "camara_id": camara_id,
            "imagen_captura": base64_image
        }
        headers = {
            "Accept": "application/json",
            "Content-Type": "application/json"
        }
        response = requests.post(url, json=payload, headers=headers, timeout=5)
        if response.status_code == 201:
            print(f"Alerta registrada en DB: Persona {persona_id} en Cam {camara_id}")
        elif response.status_code == 404:
            print(f"Alerta descartada: El usuario {persona_id} no es Persona de Interés.")
        else:
            print(f"Error registrando alerta: {response.text}")
    except Exception as e:
        print(f"Error HTTP al registrar alerta: {e}")

@app.websocket("/ws/recognition")
async def websocket_recognition(websocket: WebSocket):
    await manager.connect(websocket)
    print("Nuevo cliente conectado al sistema de retransmisión")
    
    last_recognition_time = 0
    
    try:
        while True:
            # Recibir datos (pueden ser bytes o texto)
            message = await websocket.receive()
            
            if message.get("type") == "websocket.disconnect":
                raise WebSocketDisconnect(message.get("code", 1000))
            
            data = None
            is_binary = False
            
            if "bytes" in message:
                data = message["bytes"]
                is_binary = True
            elif "text" in message:
                data = message["text"]
                is_binary = False
            
            if data:
                if is_binary:
                    # 1. Retransmitir INMEDIATAMENTE
                    await manager.broadcast_image(data, websocket, True)
                    
                    # 2. Reconocimiento Facial (Solo si son bytes/imagen)
                    current_time = time.time()
                    if (current_time - last_recognition_time > 2.0):
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
                                
                                # -- NUEVO: Registrar Alerta en Base de Datos --
                                sender_cam_id = next((cid for cid, ws in manager.locked_cameras.items() if ws == websocket), 1)
                                threading.Thread(
                                    target=enviar_alerta_laravel,
                                    args=(int(result["match_folder"]), sender_cam_id, data)
                                ).start()
                                
                        except Exception as e:
                            print(f"Error IA: {e}")
                else:
                    # ES TEXTO (JSON) - Comandos de Control
                    try:
                        cmd = json.loads(data)
                        if cmd.get("action") == "lock_camera":
                            cam_id = cmd.get("camera_id")
                            # Verificar si la cámara está ocupada por otro socket
                            if cam_id in manager.locked_cameras and manager.locked_cameras[cam_id] != websocket:
                                await websocket.send_json({"action": "lock_denied", "camera_id": cam_id})
                            else:
                                manager.locked_cameras[cam_id] = websocket
                                await websocket.send_json({"action": "lock_granted", "camera_id": cam_id})
                                # Avisar a todos los demás que la cámara se ocupó
                                await manager.broadcast_image(json.dumps({"action": "camera_status", "camera_id": cam_id, "status": "in_use"}), websocket, False)
                        elif cmd.get("action") == "unlock_camera":
                            cam_id = cmd.get("camera_id")
                            if cam_id in manager.locked_cameras and manager.locked_cameras[cam_id] == websocket:
                                del manager.locked_cameras[cam_id]
                                await manager.broadcast_image(json.dumps({"action": "camera_status", "camera_id": cam_id, "status": "free"}), websocket, False)
                    except json.JSONDecodeError:
                        await manager.broadcast_image(data, websocket, False)
                
    except (WebSocketDisconnect, RuntimeError):
        freed = manager.disconnect(websocket)
        for cam_id in freed:
            await manager.broadcast_image(json.dumps({"action": "camera_status", "camera_id": cam_id, "status": "free"}), None, False)
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
